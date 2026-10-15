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
        <div>
            <label for="status" class="sa-label">Status</label>
            <select id="status" name="status" class="sa-input pr-8">
                <option value="">Any</option>
                @foreach (\App\Enums\Billing\SubscriptionStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str_replace('_', ' ', $status->value) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="plan" class="sa-label">Plan</label>
            <select id="plan" name="plan" class="sa-input pr-8">
                <option value="">Any</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->id }}" @selected(($filters['plan'] ?? '') === $plan->id)>{{ $plan->name }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex items-center gap-2 py-2 text-sm text-zinc-700">
            <input type="checkbox" name="trial_ending" value="1" class="size-4 rounded border-zinc-300 accent-zinc-900" @checked($filters['trial_ending'] ?? false)>
            Trial ends within {{ \App\Services\SuperAdmin\SuperAdminBillingDirectory::TRIAL_ENDING_DAYS }} days
        </label>
        <label class="flex items-center gap-2 py-2 text-sm text-zinc-700">
            <input type="checkbox" name="canceling" value="1" class="size-4 rounded border-zinc-300 accent-zinc-900" @checked($filters['canceling'] ?? false)>
            Canceling
        </label>
    </x-super-admin.filters>

    <x-super-admin.card class="mt-6">
        @if ($subscriptions->isEmpty())
            <x-super-admin.empty icon="card">No subscriptions match.</x-super-admin.empty>
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
                            <tr class="hover:bg-zinc-50/70">
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

    <div class="mt-4">{{ $subscriptions->links() }}</div>
</x-super-admin.layout>
