<?php

namespace App\Services\SuperAdmin;

use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Read-only billing lookups for the super admin panel: subscriptions, the
 * credit-charge ledger, plan grants and the plan catalogue.
 */
class SuperAdminBillingDirectory
{
    private const int PER_PAGE = 50;

    /**
     * "Trial ending" means within this many days.
     */
    public const int TRIAL_ENDING_DAYS = 7;

    /**
     * @param  array<string, mixed>  $filters  validated `IndexSubscriptionsRequest` input
     * @return LengthAwarePaginator<int, Subscription>
     */
    public function subscriptions(array $filters): LengthAwarePaginator
    {
        return Subscription::query()
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('stripe_status', $status))
            ->when($filters['plan'] ?? null, fn (Builder $query, string $plan) => $query->where('plan_id', $plan))
            ->when($filters['trial_ending'] ?? false, fn (Builder $query) => $query
                ->where('stripe_status', SubscriptionStatus::Trialing->value)
                ->whereBetween('trial_ends_at', [now(), now()->addDays(self::TRIAL_ENDING_DAYS)]))
            ->when($filters['canceling'] ?? false, fn (Builder $query) => $query->whereNotNull('ends_at')->where('ends_at', '>', now()))
            ->with(['plan:id,name', 'workspace' => fn ($query) => $query->withTrashed()->select('id', 'name')])
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Subscription counts by Stripe status, for the summary row.
     *
     * @return array<string, int>
     */
    public function subscriptionCounts(): array
    {
        $counts = Subscription::query()
            ->toBase()
            ->select('stripe_status')
            ->selectRaw('count(*) as subscriptions_count')
            ->groupBy('stripe_status')
            ->pluck('subscriptions_count', 'stripe_status');

        return collect(SubscriptionStatus::cases())
            ->mapWithKeys(fn (SubscriptionStatus $status): array => [$status->value => (int) $counts->get($status->value, 0)])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters  validated `IndexCreditTransactionsRequest` input
     * @return LengthAwarePaginator<int, CreditTransaction>
     */
    public function creditTransactions(array $filters): LengthAwarePaginator
    {
        return $this->filteredCreditTransactions($filters)
            ->with(['workspace' => fn ($query) => $query->withTrashed()->select('id', 'name')])
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Totals for the same filters as the ledger page.
     *
     * @param  array<string, mixed>  $filters
     * @return array{credits: int, topup_credits: int, overage_credits: int}
     */
    public function creditTotals(array $filters): array
    {
        $totals = $this->filteredCreditTransactions($filters)
            ->toBase()
            ->selectRaw('coalesce(sum(credits), 0) as credits, coalesce(sum(topup_credits), 0) as topup_credits, coalesce(sum(overage_credits), 0) as overage_credits')
            ->first();

        return [
            'credits' => (int) $totals->credits,
            'topup_credits' => (int) $totals->topup_credits,
            'overage_credits' => (int) $totals->overage_credits,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters  validated `IndexPlanGrantsRequest` input
     * @return LengthAwarePaginator<int, PlanGrant>
     */
    public function planGrants(array $filters): LengthAwarePaginator
    {
        return PlanGrant::query()
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['workspace'] ?? null, fn (Builder $query, string $workspace) => $query->where('workspace_id', $workspace))
            ->with(['plan:id,name', 'workspace' => fn ($query) => $query->withTrashed()->select('id', 'name')])
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Every plan with how many subscriptions and active grants it carries.
     *
     * @return Collection<int, Plan>
     */
    public function plans(): Collection
    {
        $activeGrants = PlanGrant::query()
            ->active()
            ->toBase()
            ->select('plan_id')
            ->selectRaw('count(*) as grants_count')
            ->groupBy('plan_id')
            ->pluck('grants_count', 'plan_id');

        return Plan::query()
            ->withCount([
                'subscriptions as active_subscriptions_count' => fn (Builder $query) => $query
                    ->whereIn('stripe_status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value]),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(fn (Plan $plan) => $plan->setAttribute('active_grants_count', (int) $activeGrants->get($plan->id, 0)));
    }

    /**
     * @return Collection<int, Plan>
     */
    public function activePlans(): Collection
    {
        return Plan::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<CreditTransaction>
     */
    private function filteredCreditTransactions(array $filters): Builder
    {
        return CreditTransaction::query()
            ->when($filters['workspace'] ?? null, fn (Builder $query, string $workspace) => $query->where('workspace_id', $workspace))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('source_type', $type))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()));
    }
}
