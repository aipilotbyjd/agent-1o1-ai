<?php

namespace App\Models\Runs;

use App\Enums\Billing\CreditTransactionType;
use App\Enums\NodeRunStatus;
use App\Models\Billing\CreditTransaction;
use App\Models\Workflows\WorkflowApproval;
use Database\Factories\Runs\NodeRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['run_id', 'key', 'type', 'input', 'max_attempts', 'retry_delay_seconds'])]
class NodeRun extends Model
{
    /** @use HasFactory<NodeRunFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'attempt' => 1,
        'max_attempts' => 1,
        'retry_delay_seconds' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NodeRunStatus::class,
            'input' => 'array',
            'output' => 'array',
            'usage' => 'array',
            'state' => 'array',
            'attempt' => 'integer',
            'max_attempts' => 'integer',
            'retry_delay_seconds' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'callback_expires_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    public function approval(): HasOne
    {
        return $this->hasOne(WorkflowApproval::class);
    }

    /**
     * Child `runs` started against this `NodeRun` as their `parent_node_id`
     * — a `subflow` node has one, a `loop` node has one per item.
     */
    public function childRuns(): HasMany
    {
        return $this->hasMany(Run::class, 'parent_node_id');
    }

    /**
     * The `CreditTransaction` `RecordRunCreditUsage` billed this node run
     * under — null until the (queued) listener has run, or if the node run
     * never finished. `credit_transactions.source_type`/`source_id` isn't a
     * real Eloquent morph (it's a `CreditTransactionType` string, not a
     * class name), so this pins the type explicitly rather than using
     * `morphOne`.
     */
    public function creditTransaction(): HasOne
    {
        return $this->hasOne(CreditTransaction::class, 'source_id')
            ->where('source_type', CreditTransactionType::NodeRun);
    }
}
