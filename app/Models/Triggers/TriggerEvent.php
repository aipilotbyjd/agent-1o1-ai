<?php

namespace App\Models\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerType;
use App\Models\Runs\Run;
use Database\Factories\Triggers\TriggerEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'trigger_id', 'source', 'status', 'run_id', 'payload', 'payload_snippet',
    'headers', 'error', 'delivery_id', 'attempts', 'duplicate_count', 'processed_at',
])]
class TriggerEvent extends Model
{
    /** @use HasFactory<TriggerEventFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
        'attempts' => 0,
        'duplicate_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => TriggerType::class,
            'status' => TriggerEventStatus::class,
            'payload' => 'array',
            'headers' => 'array',
            'attempts' => 'integer',
            'duplicate_count' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(Trigger::class);
    }

    /**
     * The run this event started — a workflow run or an agent turn, depending
     * on the trigger's target (`App\Services\Triggers\TargetRunStarter`).
     * Null until the event reaches `fired`.
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    /**
     * Claim the event for processing: only a `queued` event can be claimed —
     * a `running` event is already claimed by whoever is processing it, and
     * claiming it again would let two workers execute the same event — and
     * the write itself is the exclusivity check: two concurrent callers racing
     * this update will see exactly one row affected between them.
     *
     * The retry-stuck command's `requeue()` call is what makes a stranded
     * `running` event claimable again — it flips the row back to `queued`
     * first, deliberately, rather than this method treating `running` as
     * claimable.
     */
    public function claim(): bool
    {
        if ($this->status->isTerminal()) {
            return false;
        }

        $affected = self::query()
            ->whereKey($this->id)
            ->where('status', TriggerEventStatus::Queued->value)
            ->update(['status' => TriggerEventStatus::Running->value]);

        if ($affected === 1) {
            $this->status = TriggerEventStatus::Running;

            return true;
        }

        return false;
    }

    /**
     * Move a stranded `queued`/`running` event back to `queued` so it can be
     * claimed again — used by the retry-stuck command before re-dispatching.
     */
    public function requeue(): void
    {
        $this->update(['status' => TriggerEventStatus::Queued]);
    }

    public function markFired(int $runId): void
    {
        $this->update([
            'status' => TriggerEventStatus::Fired,
            'run_id' => $runId,
            'processed_at' => now(),
        ]);
    }

    public function finish(TriggerEventStatus $status, ?string $error = null): void
    {
        $this->update([
            'status' => $status,
            'error' => $error,
            'processed_at' => now(),
        ]);
    }
}
