<?php

namespace App\Models\Workspaces;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Agents\Skill;
use App\Models\Auth\ApiKey;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\UsagePeriod;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

#[Fillable(['name', 'slug', 'avatar', 'owner_id'])]
class Workspace extends Model
{
    use Billable, HasFactory, SoftDeletes;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(Trigger::class);
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    public function builderSessions(): HasMany
    {
        return $this->hasMany(WorkflowBuilderSession::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function agentSessions(): HasMany
    {
        return $this->hasMany(AgentSession::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    public function documentEmbeddings(): HasMany
    {
        return $this->hasMany(DocumentEmbedding::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function usagePeriods(): HasMany
    {
        return $this->hasMany(UsagePeriod::class);
    }

    /**
     * Finds (or creates) the `UsagePeriod` covering right now — a plain
     * calendar month until Stripe billing-cycle-aligned periods are wired.
     * `credits_limit` is sized off the workspace's active plan (via its
     * `default` subscription); workspaces with no subscription stay
     * unlimited (`null`) rather than an implicit free tier.
     */
    public function currentUsagePeriod(): UsagePeriod
    {
        $startsAt = now()->startOfMonth();
        $subscription = $this->subscription('default');
        $plan = $subscription?->plan;

        return $this->usagePeriods()->firstOrCreate(
            ['starts_at' => $startsAt],
            [
                'ends_at' => $startsAt->clone()->endOfMonth()->addSecond(),
                'plan_id' => $plan?->id,
                'subscription_id' => $subscription?->id,
                'credits_limit' => $plan?->creditsMonthly(),
            ],
        );
    }
}
