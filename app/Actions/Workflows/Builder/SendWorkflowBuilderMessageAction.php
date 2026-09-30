<?php

namespace App\Actions\Workflows\Builder;

use App\Enums\Workflows\BuilderMessageStatus;
use App\Exceptions\WorkflowBuilderConflictException;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Billing\CreditGate;
use Illuminate\Support\Facades\DB;

/**
 * Records the user's message and a pending assistant message, then hands the
 * turn to `ProcessWorkflowBuilderMessageJob` — a turn can run many tool calls
 * and take minutes, far longer than a request should be held open. The
 * client follows the reply on the session's broadcast channel
 * (`WorkflowBuilderActivity`) or by polling the returned message.
 *
 * One turn at a time per session: a second message while a reply is still
 * being written would have two agents editing the same draft from two
 * different conversation histories.
 */
class SendWorkflowBuilderMessageAction
{
    public function __construct(private readonly CreditGate $creditGate) {}

    /**
     * `$sender` is who the turn's edits are attributed to — a session is
     * shared across the workspace, so it isn't always the session's owner.
     *
     * @return WorkflowBuilderMessage The pending assistant message the reply will be written to.
     *
     * @throws WorkflowBuilderConflictException
     */
    public function execute(WorkflowBuilderSession $session, string $message, ?User $sender = null): WorkflowBuilderMessage
    {
        $session->assertEditable();
        $this->creditGate->assertCanStartRun($session->workspace);

        [$userMessage, $assistantMessage] = DB::transaction(function () use ($session, $message, $sender): array {
            // Serializes concurrent sends on this session, so two requests
            // can't both see "no reply in flight" and start two turns.
            WorkflowBuilderSession::query()->whereKey($session->id)->lockForUpdate()->first();

            $session->failStaleReplies();

            if ($session->hasReplyInFlight()) {
                throw WorkflowBuilderConflictException::replyInProgress();
            }

            $userMessage = $session->messages()->create([
                'role' => 'user',
                'user_id' => $sender?->id,
                'content' => $message,
            ]);

            $assistantMessage = $session->messages()->create([
                'role' => 'assistant',
                'content' => '',
                'processing_status' => BuilderMessageStatus::Pending,
            ]);

            $session->forceFill(['last_activity_at' => now()])->save();

            return [$userMessage, $assistantMessage];
        });

        ProcessWorkflowBuilderMessageJob::dispatch($session->id, $userMessage->id, $assistantMessage->id);

        return $assistantMessage->fresh();
    }
}
