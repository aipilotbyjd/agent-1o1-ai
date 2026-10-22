<?php

namespace App\Services\Assistant\Inbox;

use App\Enums\Assistant\AssistantFollowUpStatus;
use App\Models\Assistant\AssistantFollowUp;
use App\Notifications\Assistant\FollowUpReminderNotification;
use Throwable;

/**
 * Settles one due follow-up: if anyone answered the thread since it was
 * tracked it is done, otherwise the owner is reminded. A mailbox that can't
 * be read leaves it pending, so the next check tries again.
 */
class FollowUpChecker
{
    public function __construct(
        private readonly MailboxFactory $mailboxes,
        private readonly InboxAccess $access,
    ) {}

    public function check(AssistantFollowUp $followUp): AssistantFollowUpStatus
    {
        $followUp->refresh();

        if (! $followUp->isPending() || $followUp->remind_at->isFuture()) {
            return $followUp->status;
        }

        $assistant = $followUp->assistant;
        $config = $assistant->inbox;

        // Switching Smart Inbox off, or losing access, drops what it was watching.
        if ($config === null || ! $config->enabled || ! $this->access->allows($assistant)) {
            return $this->settle($followUp, AssistantFollowUpStatus::Cancelled);
        }

        try {
            $replied = $this->mailboxes->for($config)->threadHasReplyAfter($followUp->thread_id, $followUp->created_at);
        } catch (Throwable $e) {
            report($e);

            return $followUp->status;
        }

        if ($replied) {
            return $this->settle($followUp, AssistantFollowUpStatus::Replied);
        }

        $this->settle($followUp, AssistantFollowUpStatus::Reminded);
        $assistant->user->notify(new FollowUpReminderNotification($followUp));

        return AssistantFollowUpStatus::Reminded;
    }

    private function settle(AssistantFollowUp $followUp, AssistantFollowUpStatus $status): AssistantFollowUpStatus
    {
        $followUp->forceFill(['status' => $status, 'resolved_at' => now()])->save();

        return $status;
    }
}
