<x-super-admin.layout title="Referrals" description="Every signup that came through a referral code">
    <x-super-admin.filters :action="route('super-admin.referrals.referrals.index')" :active="(bool) array_filter($filters)">
        <x-super-admin.filter-select name="status" label="Status" :value="$filters['status'] ?? null" :options="\App\Enums\Referrals\ReferralStatus::class" />
        <x-super-admin.filter-select name="program" label="Program" :value="$filters['program'] ?? null" :options="$programs->pluck('name', 'id')" />
    </x-super-admin.filters>

    <x-super-admin.card class="mt-6">
        @if ($referrals->isEmpty())
            <x-super-admin.empty :clear="array_filter($filters) ? route('super-admin.referrals.referrals.index') : null" icon="link">No referrals match.</x-super-admin.empty>
        @else
            <div class="overflow-x-auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Referred user</th>
                            <th>Referrer</th>
                            <th>Code</th>
                            <th>Program</th>
                            <th>Status</th>
                            <th>Signed up</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($referrals as $referral)
                            <tr>
                                <td><a href="{{ route('super-admin.referrals.referrals.show', $referral) }}" class="sa-row-link">{{ $referral->referredUser?->email ?? 'Deleted user' }}</a></td>
                                <td class="text-zinc-600">{{ $referral->referrer?->email ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ $referral->code?->code ?? '—' }}</td>
                                <td class="text-zinc-600">{{ $referral->program?->name ?? '—' }}</td>
                                <td><x-super-admin.status-badge :status="$referral->status" /></td>
                                <td class="whitespace-nowrap text-zinc-500">{{ $referral->created_at?->toFormattedDateString() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-super-admin.card>

    <x-super-admin.pagination :paginator="$referrals" />
</x-super-admin.layout>
