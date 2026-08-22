<?php

namespace App\Actions\Workflows\Builder;

use App\Enums\Billing\PlanLimit;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use App\Services\Billing\PlanLimitGate;
use Illuminate\Support\Str;

/**
 * Publishes a session's `draft_graph` to a real `Workflow`. The draft shape
 * already matches `Workflow::replaceGraph()`'s expected input exactly (see
 * `WorkflowBuilderSession`'s docblock), so promoting needs no transform —
 * just wiring up which workflow the graph lands on.
 */
class PromoteWorkflowBuilderSessionAction
{
    public function __construct(private readonly PlanLimitGate $limits) {}

    public function execute(WorkflowBuilderSession $session, User $by, ?string $name = null): Workflow
    {
        $workflow = $session->workflow;

        // Re-promoting into the workflow this session already owns isn't a new
        // resource, so only the first promotion is charged against the cap.
        if ($workflow === null) {
            $this->limits->assertCanCreate($session->workspace, PlanLimit::Workflows);

            $workflow = $session->workspace->workflows()->create([
                'name' => $name ?: $session->title,
                'slug' => Str::slug($name ?: $session->title).'-'.Str::random(6),
                'created_by' => $by->id,
            ]);
        }

        $workflow->replaceGraph($session->currentGraph());

        $session->update(['workflow_id' => $workflow->id, 'status' => 'promoted']);

        return $workflow;
    }
}
