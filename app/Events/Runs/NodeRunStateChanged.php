<?php

namespace App\Events\Runs;

use App\Broadcasting\Channels;
use App\Models\Runs\NodeRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * One step of a run changed state — what lights a node up on an open canvas.
 * Broadcast only on the run's own channel, never the workspace-wide one: a
 * fan-out graph can settle dozens of nodes a second, which a runs *list* has
 * no use for.
 *
 * `output` is deliberately excluded — it can be megabytes (an API node's
 * response body) and is fetched on demand from the node-run detail endpoint
 * when a node is actually opened.
 */
class NodeRunStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly NodeRun $nodeRun) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(Channels::run($this->nodeRun->run))];
    }

    public function broadcastAs(): string
    {
        return 'node-run.state-changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->nodeRun->id,
            'run_id' => $this->nodeRun->run_id,
            'key' => $this->nodeRun->key,
            'type' => $this->nodeRun->type,
            'status' => $this->nodeRun->status->value,
            'attempt' => $this->nodeRun->attempt,
            'error' => $this->nodeRun->error,
            'started_at' => $this->nodeRun->started_at?->toIso8601String(),
            'finished_at' => $this->nodeRun->finished_at?->toIso8601String(),
        ];
    }
}
