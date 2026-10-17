<x-super-admin.layout title="Referral codes" description="Each user's personal referral code">
    <x-super-admin.filters :action="route('super-admin.referrals.codes.index')" :active="(bool) array_filter($filters)">
        <x-super-admin.search :value="$filters['q'] ?? ''" placeholder="Search by code, email or name" />
    </x-super-admin.filters>

    <x-super-admin.card class="mt-6">
        @if ($codes->isEmpty())
            <x-super-admin.empty :clear="array_filter($filters) ? route('super-admin.referrals.codes.index') : null" icon="hashtag">No codes match.</x-super-admin.empty>
        @else
            <div class="overflow-x-auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Owner</th>
                            <th>Program</th>
                            <th>Status</th>
                            <th class="text-right">Visits</th>
                            <th class="text-right">Referrals</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($codes as $code)
                            <tr>
                                <td class="font-mono text-xs font-semibold">{{ $code->code }}</td>
                                <td>
                                    @if ($code->user)
                                        <a href="{{ route('super-admin.users.show', $code->user) }}" class="sa-link">{{ $code->user->email }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-zinc-600">{{ $code->program?->name ?? 'Default' }}</td>
                                <td><x-super-admin.status-badge :status="$code->is_active && ! $code->expires_at?->isPast() ? 'active' : 'disabled'" /></td>
                                <td class="text-right tabular-nums">{{ number_format($code->visits_count) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($code->referrals_count) }}</td>
                                <td class="text-right"><a href="{{ route('super-admin.referrals.codes.edit', $code) }}" class="sa-btn-secondary">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-super-admin.card>

    <x-super-admin.pagination :paginator="$codes" />
</x-super-admin.layout>
