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

    /**
     * How much of this period's plan allowance is left, ignoring any
     * non-expiring top-up credits the workspace holds. `0` once the
     * allowance is spent; meaningless (and not called) on an unlimited
     * period, where `credits_limit` is `null`.
     */
    public function remainingPlanCredits(): int
    {
        if ($this->credits_limit === null) {
            return 0;
        }

        return max(0, $this->credits_limit - $this->credits_used);
    }
}
