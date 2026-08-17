<?php

namespace App\Services\Agents;

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Tools\ExportArtifactTool;
use App\Ai\Tools\NodeTool;
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
 * a `SearchKnowledgeTool` auto-attached whenever the workspace has any
 * `document_embeddings` rows at all, and a `RememberTool` attached
 * unconditionally so every agent can save durable facts. See
 * docs/AGENTS_PLAN.md's "Models & tool binding" and "Knowledge / RAG"
 * sections.
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
    ) {}

    /**
     * @return array<int, NodeTool|WorkflowTool|SearchKnowledgeTool|RememberTool|ExportArtifactTool>
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

        $knowledgeTools = DocumentEmbedding::where('workspace_id', $agent->workspace_id)->exists()
            ? [new SearchKnowledgeTool($agent->workspace)]
            : [];

        $memoryTools = [new RememberTool($agent, $run->triggered_by)];

        $artifactTools = $run->runnable instanceof AgentSession
            ? [new ExportArtifactTool($agent, $run->runnable, $run)]
            : [];

        return [
            ...$nodeTools->values()->all(),
            ...$workflowTools->values()->all(),
            ...$knowledgeTools,
            ...$memoryTools,
            ...$artifactTools,
        ];
    }
}
