<?php

namespace App\Actions\Workflows\Builder;

use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;

class CreateWorkflowBuilderSessionAction
{
    public function execute(Workspace $workspace, User $user, ?string $title = null, ?Workflow $workflow = null): WorkflowBuilderSession
    {
        return $workspace->builderSessions()->create([
            'user_id' => $user->id,
            'workflow_id' => $workflow?->id,
            ...($title ? ['title' => $title] : []),
            'draft_graph' => $workflow ? $this->graphFrom($workflow) : ['nodes' => [], 'edges' => []],
        ]);
    }

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    private function graphFrom(Workflow $workflow): array
    {
        $workflow->loadMissing(['nodes', 'edges']);
        $keysById = $workflow->nodes->pluck('key', 'id');

        return [
            'nodes' => $workflow->nodes->map(fn ($node) => [
                'key' => $node->key,
                'type' => $node->type,
                'config' => $node->config ?? [],
                'position' => $node->position,
            ])->values()->all(),
            'edges' => $workflow->edges->map(fn ($edge) => [
                'from' => $keysById[$edge->from_node_id],
                'to' => $keysById[$edge->to_node_id],
                'condition' => $edge->condition,
            ])->values()->all(),
        ];
    }
}
