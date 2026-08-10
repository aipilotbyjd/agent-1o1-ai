<?php

namespace App\Models\Billing;

use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `credits_used` is engine-managed (incremented by
 * `DeductCreditsAction` via `increment()`, never mass-assigned) — only the
 * window bounds and an optional limit are user/system-set at creation time.
 */
#[Fillable(['workspace_id', 'plan_id', 'subscription_id', 'starts_at', 'ends_at', 'credits_limit'])]
class UsagePeriod extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'credits_used' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'credits_used' => 'integer',
            'credits_limit' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function hasRemainingCredits(int $credits): bool
    {
        return $this->credits_limit === null || ($this->credits_used + $credits) <= $this->credits_limit;
    }
}
