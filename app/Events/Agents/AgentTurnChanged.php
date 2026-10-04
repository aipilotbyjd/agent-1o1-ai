<?php

namespace App\Events\Agents;

use App\Broadcasting\Channels;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A chat turn moved: started streaming, paused for approvals, finished or
 * failed. Anything but `running` is final for this stretch of the turn —
 * the chat swaps its live draft for the stored message (`message_id`) and,
 * for `awaiting_approval`, fetches the waiting actions
 * (`pending_action_ids`). `error` is a message that is safe to show; the
 * raw failure is on the run.
 */
class AgentTurnChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly AgentSession $session,
        public readonly Run $run,
        public readonly ?string $error = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(Channels::agentSession($this->session));
    }

    public function broadcastAs(): string
    {
        return 'turn.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'turn' => array_filter([
                'run_id' => $this->run->id,
                'agent_session_id' => $this->session->id,
                'status' => $this->run->status->value,
                'message_id' => $this->run->output['message_id'] ?? null,
                'pending_action_ids' => $this->run->output['pending_action_ids'] ?? null,
                'error' => $this->error,
            ], fn (mixed $value): bool => $value !== null),
        ];
    }
}
