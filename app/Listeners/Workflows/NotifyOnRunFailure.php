<?php

namespace App\Listeners\Workflows;

use App\Enums\NodeRunStatus;
use App\Events\Runs\RunFailed;
use App\Models\Runs\Run;
use App\Notifications\Workspace\RunFailedNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Workflows\NodeTester;

/**
 * Tells a workspace's owners and admins that one of their runs failed, over
 * whatever channels each of them has enabled for `run.failed`
 * (`WorkspaceEventNotification::via()`).
 *
 * Two kinds of `RunFailed` deliberately produce no notification — see
 * `isWorthNotifying()`. Both are cases where a notification would be either a
 * duplicate or noise, and getting them wrong is how a failure feed becomes
 * something people mute.
 */
class NotifyOnRunFailure
{
    public function __construct(private readonly NotificationDispatcher $notifications) {}

    public function handle(RunFailed $event): void
    {
        $run = $event->run;

        if (! $this->isWorthNotifying($run)) {
            return;
        }

        $workspace = $run->workspace;

        if ($workspace === null) {
            return;
        }

        $this->notifications->dispatch(
            $this->notifications->ownersAndAdmins($workspace),
            new RunFailedNotification($workspace, $run, $this->failedNodeKey($run)),
        );
    }

    /**
     * - A **child run** (`parent_run_id` set) is one iteration of a Loop or one
     *   SubWorkflow call. Its failure propagates to the parent, which fires its
     *   own `RunFailed` — so notifying here would mean N+1 notifications for a
     *   single user-visible failure, and would name a run the user never
     *   started.
     * - A **node test** is the editor's "test this step" button. Its failing is
     *   the expected, useful outcome of clicking it (`NodeTester::test()` records
     *   the error rather than throwing), and the person who clicked is already
     *   looking at the result.
     */
    private function isWorthNotifying(Run $run): bool
    {
        return $run->parent_run_id === null
            && $run->trigger_type !== NodeTester::TRIGGER_TYPE;
    }

    /**
     * The node the run died on, for deep-linking into the run log. Best-effort
     * — a run can fail before any node runs (a credit gate, a missing version),
     * in which case there is simply nothing to point at.
     */
    private function failedNodeKey(Run $run): ?string
    {
        return $run->nodeRuns()
            ->where('status', NodeRunStatus::Failed)
            ->orderByDesc('finished_at')
            ->value('key');
    }
}
