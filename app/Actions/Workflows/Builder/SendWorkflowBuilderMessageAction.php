<?php

namespace App\Actions\Workflows\Builder;

use App\Enums\Workflows\BuilderMessageStatus;
use App\Exceptions\WorkflowBuilderConflictException;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
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
    /**
     * A reply still "in flight" after this long lost its worker without the
     * job's `failed()` hook running (a hard kill, a deploy mid-turn). It is
     * failed here so the session isn't blocked forever. Comfortably above
     * the job's own timeout.
     */
    public const int STALE_REPLY_MINUTES = 10;

    public function __construct(private readonly CreditGate $creditGate) {}

    /**
     * @return WorkflowBuilderMessage The pending assistant message the reply will be written to.
     *
     * @throws WorkflowBuilderConflictException
     */
    public function execute(WorkflowBuilderSession $session, string $message): WorkflowBuilderMessage
    {
        $session->assertEditable();
        $this->creditGate->assertCanStartRun($session->workspace);

        [$userMessage, $assistantMessage] = DB::transaction(function () use ($session, $message): array {
            // Serializes concurrent sends on this session, so two requests
            // can't both see "no reply in flight" and start two turns.
            WorkflowBuilderSession::query()->whereKey($session->id)->lockForUpdate()->first();

            $this->failStaleReplies($session);

            if ($session->hasReplyInFlight()) {
                throw WorkflowBuilderConflictException::replyInProgress();
            }

            $userMessage = $session->messages()->create([
                'role' => 'user',
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

    private function failStaleReplies(WorkflowBuilderSession $session): void
    {
        $session->messages()
            ->where('role', 'assistant')
            ->whereIn('processing_status', [BuilderMessageStatus::Pending, BuilderMessageStatus::Processing])
            ->where('updated_at', '<', now()->subMinutes(self::STALE_REPLY_MINUTES))
            ->update([
                'processing_status' => BuilderMessageStatus::Failed,
                'error_message' => ProcessWorkflowBuilderMessageJob::FAILURE_MESSAGE,
                'updated_at' => now(),
            ]);
    }
}
