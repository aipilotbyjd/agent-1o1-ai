<?php

namespace App\Services\Agents;

use App\Actions\Artifacts\StoreArtifactAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Tools\ExportArtifactTool;
use App\Ai\Tools\NodeTool;
use App\Ai\Tools\ReadKnowledgeDocumentTool;
use App\Ai\Tools\RememberTool;
use App\Ai\Tools\SearchKnowledgeTool;
use App\Ai\Tools\WorkflowTool;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Agents\AgentToolBinding;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Runs\Run;
use App\Services\Workflows\NodeRegistry;

/**
 * Builds the actual `Laravel\Ai` tool list handed to the model at agent-run
 * time — one `NodeTool` per attached `agent_node` row (types no longer in
 * `NodeRegistry`, e.g. a removed custom node, are silently skipped rather
 * than erroring the whole turn), one `WorkflowTool` per attached `Workflow`,
 * and a `RememberTool` attached unconditionally so every agent can save
 * durable facts. See docs/AGENTS_PLAN.md's "Models & tool binding" and
 * "Knowledge / RAG" sections.
 *
 * Knowledge tools (`SearchKnowledgeTool`/`ReadKnowledgeDocumentTool`) follow
 * Gumloop's "attach a source to use it" model — see `knowledgeTools()`.
 *
 * `ExportArtifactTool` is only attached for a session-backed chat turn
 * (`$run->runnable` is an `AgentSession`) — `ask()`'s embedded, session-less
 * calls have nowhere to file an artifact under, so they skip it.
 */
class ToolRegistry
{
    public function __construct(
        private readonly NodeRegistry $nodes,
        private readonly StartWorkflowRunAction $startWorkflowRun,
        private readonly StoreArtifactAction $storeArtifact,
        private readonly KnowledgeBase $knowledgeBase,
    ) {}

    /**
     * @return array<int, NodeTool|WorkflowTool|SearchKnowledgeTool|ReadKnowledgeDocumentTool|RememberTool|ExportArtifactTool>
     */
    public function toolsFor(Agent $agent, Run $run): array
    {
        $nodeTools = $agent->toolBindings()
            ->get()
            ->filter(fn (AgentToolBinding $binding) => $this->nodes->has($binding->node_type))
            ->map(fn (AgentToolBinding $binding) => new NodeTool($this->nodes->resolve($binding->node_type), $binding, $run));

        $workflowTools = $agent->workflows()
            ->get()
            ->map(fn ($workflow) => new WorkflowTool($workflow, $this->startWorkflowRun));

        $knowledgeTools = $this->knowledgeTools($agent);

        $memoryTools = [new RememberTool($agent, $run->triggered_by)];

        $artifactTools = $run->runnable instanceof AgentSession
            ? [new ExportArtifactTool($agent, $run->runnable, $run, $this->storeArtifact)]
            : [];

        return [
            ...$nodeTools->values()->all(),
            ...$workflowTools->values()->all(),
            ...$knowledgeTools,
            ...$memoryTools,
            ...$artifactTools,
        ];
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
