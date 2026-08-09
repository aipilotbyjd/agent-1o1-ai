<?php

namespace App\Actions\Workflows\Builder;

use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use Illuminate\Support\Str;

/**
 * Publishes a session's `draft_graph` to a real `Workflow`. The draft shape
 * already matches `Workflow::replaceGraph()`'s expected input exactly (see
 * `WorkflowBuilderSession`'s docblock), so promoting needs no transform —
 * just wiring up which workflow the graph lands on.
 */
class PromoteWorkflowBuilderSessionAction
{
    public function execute(WorkflowBuilderSession $session, User $by, ?string $name = null): Workflow
    {
        $workflow = $session->workflow ?? $session->workspace->workflows()->create([
            'name' => $name ?: $session->title,
            'slug' => Str::slug($name ?: $session->title).'-'.Str::random(6),
            'created_by' => $by->id,
        ]);

        $workflow->replaceGraph($session->currentGraph());

        $session->update(['workflow_id' => $workflow->id, 'status' => 'promoted']);

        return $workflow;
    }
}
