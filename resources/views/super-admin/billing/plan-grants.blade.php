<x-super-admin.layout title="Plan grants" description="Plans held outside a subscription: lifetime purchases, referral time and manual grants">
    <x-super-admin.filters :action="route('super-admin.billing.plan-grants.index')" :active="(bool) array_filter($filters)">
        <div>
            <label for="source" class="sa-label">Source</label>
            <select id="source" name="source" class="sa-input pr-8">
                <option value="">Any</option>
                @foreach (\App\Enums\Billing\PlanGrantSource::cases() as $source)
                    <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ str_replace('_', ' ', $source->value) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="sa-label">Status</label>
            <select id="status" name="status" class="sa-input pr-8">
                <option value="">Any</option>
                @foreach (\App\Enums\Billing\PlanGrantStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->value }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-72 flex-1">
            <label for="workspace" class="sa-label">Workspace ID</label>
            <input id="workspace" name="workspace" type="text" value="{{ $filters['workspace'] ?? '' }}" class="sa-input font-mono">
        </div>
    </x-super-admin.filters>

    <x-super-admin.card class="mt-6">
        @if ($grants->isEmpty())
            <x-super-admin.empty icon="gift">No plan grants match.</x-super-admin.empty>
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
                            <tr class="hover:bg-zinc-50/70">
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

    <div class="mt-4">{{ $grants->links() }}</div>
</x-super-admin.layout>
