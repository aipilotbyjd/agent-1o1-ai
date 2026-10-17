<x-super-admin.layout title="Plan grants" description="Plans held outside a subscription: lifetime purchases, referral time and manual grants">
    <x-super-admin.filters :action="route('super-admin.billing.plan-grants.index')" :active="(bool) array_filter($filters)">
        <x-super-admin.filter-select name="source" label="Source" :value="$filters['source'] ?? null" :options="\App\Enums\Billing\PlanGrantSource::class" />
        <x-super-admin.filter-select name="status" label="Status" :value="$filters['status'] ?? null" :options="\App\Enums\Billing\PlanGrantStatus::class" />
        <x-super-admin.filter-text name="workspace" label="Workspace" :value="$filters['workspace'] ?? null" placeholder="Paste a workspace ID" mono class="w-full sm:w-96" />
    </x-super-admin.filters>

    <x-super-admin.card class="mt-6">
        @if ($grants->isEmpty())
            <x-super-admin.empty :clear="array_filter($filters) ? route('super-admin.billing.plan-grants.index') : null" icon="gift">No plan grants match.</x-super-admin.empty>
        @else
            <div class="overflow-x-auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Workspace</th>
                            <th>Plan</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Granted</th>
                            <th>Expires</th>
                            <th class="text-right">Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grants as $grant)
                            <tr>
                                <td>
                                    @if ($grant->workspace)
                                        <a href="{{ route('super-admin.workspaces.show', $grant->workspace) }}" class="sa-link">{{ $grant->workspace->name }}</a>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td>{{ $grant->plan?->name ?? '—' }}</td>
                                <td class="capitalize">{{ str_replace('_', ' ', $grant->source->value) }}</td>
                                <td><x-super-admin.status-badge :status="$grant->status->value === 'active' && $grant->expires_at?->isPast() ? 'expired' : $grant->status" /></td>
                                <td class="whitespace-nowrap text-zinc-500">{{ $grant->granted_at?->toFormattedDateString() ?? '—' }}</td>
                                <td class="whitespace-nowrap text-zinc-500">{{ $grant->expires_at?->toFormattedDateString() ?? 'Never' }}</td>
                                <td class="text-right font-mono tabular-nums">{{ $grant->price_cents > 0 ? strtoupper($grant->currency).' '.number_format($grant->price_cents / 100, 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-super-admin.card>

    <x-super-admin.pagination :paginator="$grants" />
</x-super-admin.layout>
