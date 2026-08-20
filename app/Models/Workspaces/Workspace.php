<?php

namespace App\Models\Workspaces;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Agents\Skill;
use App\Models\Artifacts\Artifact;
use App\Models\Auth\ApiKey;
use App\Models\Billing\CreditPack;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\Subscription;
use App\Models\Billing\UsagePeriod;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Nodes\CustomNode;
use App\Models\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationPreference;
use App\Models\Runs\Run;
use App\Models\Templates\AgentTemplate;
use App\Models\Templates\TemplateCollection;
use App\Models\Templates\WorkflowTemplate;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Folder;
use App\Models\Workflows\Tag;
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

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'topup_credits' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topup_credits' => 'integer',
        ];
    }

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

    public function notificationChannels(): HasMany
    {
        return $this->hasMany(NotificationChannel::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
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

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
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

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    public function customNodes(): HasMany
    {
        return $this->hasMany(CustomNode::class);
    }

    public function connectorCredentials(): HasMany
    {
        return $this->hasMany(ConnectorCredential::class);
    }

    public function documentEmbeddings(): HasMany
    {
        return $this->hasMany(DocumentEmbedding::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function creditPacks(): HasMany
    {
        return $this->hasMany(CreditPack::class);
    }

    /**
     * @return HasMany<PlanGrant, $this>
     */
    public function planGrants(): HasMany
    {
        return $this->hasMany(PlanGrant::class);
    }

    public function usagePeriods(): HasMany
    {
        return $this->hasMany(UsagePeriod::class);
    }

    public function workflowTemplates(): HasMany
    {
        return $this->hasMany(WorkflowTemplate::class);
    }

    public function agentTemplates(): HasMany
    {
        return $this->hasMany(AgentTemplate::class);
    }

    public function templateCollections(): HasMany
    {
        return $this->hasMany(TemplateCollection::class);
    }

    /**
     * The subscription whose plan currently entitles this workspace —
     * `null` unless Cashier considers it `valid()` (active, on trial, or on
     * its grace period). Cashier's `subscription()` returns the row whatever
     * its status, so reading entitlements straight off it would keep a
     * canceled or `past_due` workspace on its paid plan indefinitely.
     */
    public function activeSubscription(): ?Subscription
    {
        $subscription = $this->subscription('default');

        return $subscription?->valid() ? $subscription : null;
    }

    /**
     * The plan whose credits and limits apply right now, resolved from two
     * independent sources: an active `PlanGrant` (a lifetime purchase or a
     * comp) and a valid Cashier subscription. When a workspace holds both,
     * the *more generous* of the two applies — a lifetime Starter holder who
     * later subscribes to Pro must not be silently downgraded to what they
     * bought outright, and a Pro subscriber who buys a lifetime Starter must
     * not lose their Pro allowance. Falls back to the configured default
     * (Free) plan when neither entitles, so "unsubscribed" means the free
     * tier and not an unmetered one.
     */
    public function currentPlan(): ?Plan
    {
        $candidates = collect([
            $this->activePlanGrant()?->plan,
            $this->activeSubscription()?->plan,
        ])->filter();

        if ($candidates->isEmpty()) {
            return Plan::default();
        }

        return $candidates->sortByDesc(fn (Plan $plan): int => $plan->creditsMonthly())->first();
    }

    /**
     * The workspace's non-subscription entitlement, if any — a lifetime
     * purchase or a comped grant. The most recently granted one wins when a
     * workspace holds several (e.g. it upgraded from a lifetime Starter to a
     * lifetime Pro), since `currentPlan()` only weighs one grant against the
     * subscription.
     */
    public function activePlanGrant(): ?PlanGrant
    {
        return $this->planGrants()
            ->active()
            ->with('plan')
            ->orderByDesc('granted_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Finds (or creates) the `UsagePeriod` covering right now — a plain
     * calendar month until Stripe billing-cycle-aligned periods are wired.
     * `credits_limit` is sized off `currentPlan()`, so an unsubscribed
     * workspace lands on the Free plan's allowance instead of an unlimited
     * one. Stays `null` (unlimited) only when no default plan is seeded.
     */
    public function currentUsagePeriod(): UsagePeriod
    {
        $startsAt = now()->startOfMonth();
        $subscription = $this->activeSubscription();
        $plan = $this->currentPlan();

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

    /**
     * Plan allowance left this period plus the non-expiring `topup_credits`
     * pool bought via credit packs. `null` means unlimited.
     */
    public function availableCredits(): ?int
    {
        $period = $this->currentUsagePeriod();

        if ($period->credits_limit === null) {
            return null;
        }

        return $period->remainingPlanCredits() + $this->topup_credits;
    }
}
