<x-super-admin.layout title="Subscriptions" description="Stripe subscriptions across every workspace">
    <section class="mb-6 flex flex-wrap gap-2">
        @foreach ($counts as $status => $count)
            <a href="{{ route('super-admin.billing.subscriptions.index', ['status' => $status]) }}" @class([
                'inline-flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-sm shadow-xs ring-1 transition hover:ring-zinc-300',
                'ring-indigo-500' => ($filters['status'] ?? '') === $status,
                'ring-zinc-200/80' => ($filters['status'] ?? '') !== $status,
            ])>
                <x-super-admin.status-badge :status="$status" />
                <span class="font-semibold tabular-nums">{{ number_format($count) }}</span>
            </a>
        @endforeach
    </section>

    <x-super-admin.filters :action="route('super-admin.billing.subscriptions.index')" :active="(bool) array_filter($filters)">
        <x-super-admin.filter-select name="status" label="Status" :value="$filters['status'] ?? null" :options="\App\Enums\Billing\SubscriptionStatus::class" />
        <x-super-admin.filter-select name="plan" label="Plan" :value="$filters['plan'] ?? null" :options="$plans->pluck('name', 'id')" />
        <x-super-admin.filter-toggle name="trial_ending" :checked="$filters['trial_ending'] ?? false">Trial ends within {{ \App\Services\SuperAdmin\SuperAdminBillingDirectory::TRIAL_ENDING_DAYS }} days</x-super-admin.filter-toggle>
        <x-super-admin.filter-toggle name="canceling" :checked="$filters['canceling'] ?? false">Canceling</x-super-admin.filter-toggle>
    </x-super-admin.filters>

    <x-super-admin.card class="mt-6">
        @if ($subscriptions->isEmpty())
            <x-super-admin.empty :clear="array_filter($filters) ? route('super-admin.billing.subscriptions.index') : null" icon="card">No subscriptions match.</x-super-admin.empty>
        @else
            <div class="overflow-x-auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Workspace</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Trial ends</th>
                            <th>Ends</th>
                            <th>Started</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subscriptions as $subscription)
                            <tr>
                                <td>
                                    @if ($subscription->workspace)
                                        <a href="{{ route('super-admin.workspaces.show', $subscription->workspace) }}" class="sa-link">{{ $subscription->workspace->name }}</a>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                    <p class="font-mono text-xs text-zinc-400">{{ $subscription->stripe_id }}</p>
                                </td>
                                <td>{{ $subscription->plan?->name ?? '—' }}</td>
                                <td><x-super-admin.status-badge :status="$subscription->stripe_status" /></td>
                                <td class="whitespace-nowrap text-zinc-500">{{ $subscription->trial_ends_at?->toFormattedDateString() ?? '—' }}</td>
                                <td class="whitespace-nowrap text-zinc-500">{{ $subscription->ends_at?->toFormattedDateString() ?? '—' }}</td>
                                <td class="whitespace-nowrap text-zinc-500">{{ $subscription->created_at?->toFormattedDateString() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-super-admin.card>

    <x-super-admin.pagination :paginator="$subscriptions" />
</x-super-admin.layout>
