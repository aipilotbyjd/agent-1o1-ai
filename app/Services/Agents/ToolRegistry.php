<?php

namespace App\Services\Agents;

use App\Actions\Artifacts\StoreArtifactAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Tools\CreateSkillTool;
use App\Ai\Tools\ExportArtifactTool;
use App\Ai\Tools\InvokeAgentTool;
use App\Ai\Tools\NodeTool;
use App\Ai\Tools\ReadKnowledgeDocumentTool;
use App\Ai\Tools\RememberTool;
use App\Ai\Tools\SearchKnowledgeTool;
use App\Ai\Tools\UpdateInstructionsTool;
use App\Ai\Tools\UpdateSkillTool;
use App\Ai\Tools\UseSkillTool;
use App\Ai\Tools\WaitForSubagentsTool;
use App\Ai\Tools\WorkflowTool;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Agents\AgentToolBinding;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Runs\Run;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Artifacts\DocumentRenderer;
use App\Services\Workflows\NodeRegistry;
use Laravel\Ai\AiManager;
use Laravel\Ai\Contracts\Providers\SupportsWebFetch;
use Laravel\Ai\Contracts\Providers\SupportsWebSearch;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * Builds the actual `Laravel\Ai` tool list handed to the model at agent-run
 * time — one `NodeTool` per attached `agent_node` row (types no longer in
 * `NodeRegistry`, e.g. a removed custom node, are silently skipped rather
 * than erroring the whole turn), one `WorkflowTool` per attached `Workflow`,
 * a `UseSkillTool` when the agent has skills attached, and a `RememberTool`
 * attached unconditionally so every agent can save durable facts. See docs/AGENTS_PLAN.md's "Models & tool binding" and
 * "Knowledge / RAG" sections.
 *
 * Knowledge tools (`SearchKnowledgeTool`/`ReadKnowledgeDocumentTool`) follow
 * Gumloop's "attach a source to use it" model — see `knowledgeTools()`.
 *
 * `ExportArtifactTool` is only attached when there's an `AgentSession` to
 * file an artifact under — normally `$run->runnable` for a session-backed
 * chat turn, or the explicit `$session` a caller with no session-backed
 * `$run` passes in (`AgentRunner::askInConversation()`, for an Agent node's
 * own conversation). `ask()`'s stateless embedded calls pass neither and so
 * skip it, same as before. `UpdateInstructionsTool` has the same session
 * requirement, so a stateless eval case can never rewrite the agent under
 * test, and is only attached when the agent has `allow_self_updates` on.
 * `CreateSkillTool`/`UpdateSkillTool` follow the same rule, gated on
 * `allow_skill_editing` instead. `InvokeAgentTool` also needs a session, and is
 * only offered in a top-level conversation — see `subagentTools()`.
 *
 * Web search and page fetching are the SDK's provider-native `WebSearch`/
 * `WebFetch`, run by the model provider itself — see `webTools()`.
 */
class ToolRegistry
{
    public function __construct(
        private readonly NodeRegistry $nodes,
        private readonly StartWorkflowRunAction $startWorkflowRun,
        private readonly StoreArtifactAction $storeArtifact,
        private readonly KnowledgeBase $knowledgeBase,
        private readonly SkillInjector $skillInjector,
        private readonly ModelCatalogResolver $modelCatalog,
        private readonly AiManager $ai,
        private readonly DocumentRenderer $documentRenderer,
    ) {}

    /**
     * @return array<int, NodeTool|WorkflowTool|SearchKnowledgeTool|ReadKnowledgeDocumentTool|UseSkillTool|CreateSkillTool|UpdateSkillTool|RememberTool|ExportArtifactTool|UpdateInstructionsTool|InvokeAgentTool|WaitForSubagentsTool|WebSearch|WebFetch>
     */
    public function toolsFor(Agent $agent, Run $run, ?AgentSession $session = null): array
    {
        $nodeTools = $agent->toolBindings()
            ->get()
            ->filter(fn (AgentToolBinding $binding) => $this->nodes->has($binding->node_type))
            ->map(fn (AgentToolBinding $binding) => new NodeTool($this->nodes->resolve($binding->node_type), $binding, $run));

        $workflowTools = $agent->workflows()
            ->get()
            ->map(fn ($workflow) => new WorkflowTool($workflow, $this->startWorkflowRun));

        $knowledgeTools = $this->knowledgeTools($agent);

        $skillTools = $agent->skills->isNotEmpty() ? [new UseSkillTool($agent)] : [];

        $memoryTools = [new RememberTool($agent, $run->triggered_by)];

        $session ??= $run->runnable instanceof AgentSession ? $run->runnable : null;

        $artifactTools = $session !== null
            ? [new ExportArtifactTool($agent, $session, $run, $this->storeArtifact, $this->documentRenderer)]
            : [];

        $skillEditingTools = $session !== null && $agent->allow_skill_editing
            ? array_values(array_filter([
                new CreateSkillTool($agent, $run->triggered_by),
                $agent->skills->isNotEmpty() ? new UpdateSkillTool($agent, $run->triggered_by) : null,
            ]))
            : [];

        $selfUpdateTools = $session !== null && $agent->allow_self_updates
            ? [new UpdateInstructionsTool($agent, $this->skillInjector, $run->triggered_by, $session)]
            : [];

        $subagentTools = $session !== null ? $this->subagentTools($agent, $session) : [];

        return [
            ...$nodeTools->values()->all(),
            ...$workflowTools->values()->all(),
            ...$knowledgeTools,
            ...$skillTools,
            ...$skillEditingTools,
            ...$memoryTools,
            ...$artifactTools,
            ...$selfUpdateTools,
            ...$subagentTools,
            ...$this->webTools($agent),
        ];
    }

    /**
     * `InvokeAgentTool` for a top-level conversation only: "Me" when the
     * agent allows self-cloning, plus its attached subagents. A subagent's
     * own conversation never delegates further — its job runs on an
     * `ai-subagent` worker, and one that waited on children queued behind it
     * on the same workers could leave every worker waiting and none free to
     * run them.
     *
     * Targets are keyed by the name the model uses, so a subagent whose name
     * clashes with "Me" or another subagent gets a numbered suffix rather
     * than silently replacing it.
     *
     * @return array<int, InvokeAgentTool|WaitForSubagentsTool>
     */
    private function subagentTools(Agent $agent, AgentSession $session): array
    {
        if ($session->parent_session_id !== null) {
            return [];
        }

        $targets = [];

        if ($agent->allow_self_clone) {
            $targets[InvokeAgentTool::SELF] = $agent;
        }

        foreach ($agent->subagents as $subagent) {
            $targets[$this->uniqueTargetName($subagent->name, $targets)] = $subagent;
        }

        return $targets === [] ? [] : [new InvokeAgentTool($session, $targets), new WaitForSubagentsTool($session)];
    }

    /**
     * @param  array<string, Agent>  $targets
     */
    private function uniqueTargetName(string $name, array $targets): string
    {
        $taken = array_map(mb_strtolower(...), array_keys($targets));
        $candidate = $name;

        for ($suffix = 2; in_array(mb_strtolower($candidate), $taken, true); $suffix++) {
            $candidate = "{$name} ({$suffix})";
        }

        return $candidate;
    }

    /**
     * Only offered when every provider in the agent's failover chain runs
     * the tool natively: an unsupported provider (any `openai-compatible`
     * gateway, for one) throws on a provider tool rather than ignoring it,
     * which would fail the whole turn once failover reached it.
     *
     * @return array<int, WebSearch|WebFetch>
     */
    private function webTools(Agent $agent): array
    {
        [$provider] = $this->modelCatalog->forAgent($agent);

        $providers = collect(is_array($provider) ? array_keys($provider) : [$provider])
            ->map(fn (?string $name) => $this->ai->textProvider($name));

        return array_values(array_filter([
            $providers->every(fn ($instance) => $instance instanceof SupportsWebSearch) ? new WebSearch : null,
            $providers->every(fn ($instance) => $instance instanceof SupportsWebFetch) ? new WebFetch : null,
        ]));
    }

    /**
     * Scoped to what this agent may actually search: its explicitly
     * attached `AgentKnowledgeCollection`s, plus its own exported artifacts
     * (always implicitly searchable — see `Agent::artifactKnowledgeCollection()`).
     * An agent with neither falls back to every collection in the
     * workspace, the zero-config behavior this had before per-agent
     * attachment existed.
     *
     * @return array<int, SearchKnowledgeTool|ReadKnowledgeDocumentTool>
     */
    private function knowledgeTools(Agent $agent): array
    {
        $attached = $agent->knowledgeCollections()->pluck('collection')->all();

        $artifactCollection = $agent->artifactKnowledgeCollection();
        $hasOwnArtifactChunks = DocumentEmbedding::query()
            ->where('workspace_id', $agent->workspace_id)
            ->where('collection', $artifactCollection)
            ->exists();

        $scoped = $hasOwnArtifactChunks ? [...$attached, $artifactCollection] : $attached;

        $collection = match (true) {
            $scoped !== [] => $scoped,
            DocumentEmbedding::query()->where('workspace_id', $agent->workspace_id)->exists() => null,
            default => false,
        };

        if ($collection === false) {
            return [];
        }

        return [
            new SearchKnowledgeTool($agent->workspace, $collection, $this->knowledgeBase),
            new ReadKnowledgeDocumentTool($agent->workspace, $collection, $this->knowledgeBase),
        ];
    }
}
