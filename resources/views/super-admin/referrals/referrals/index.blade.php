<x-super-admin.layout title="Referrals" description="Every signup that came through a referral code">
    <x-super-admin.filters :action="route('super-admin.referrals.referrals.index')" :active="(bool) array_filter($filters)">
        <div>
            <label for="status" class="sa-label">Status</label>
            <select id="status" name="status" class="sa-input pr-8">
                <option value="">Any</option>
                @foreach (\App\Enums\Referrals\ReferralStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->value }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="program" class="sa-label">Program</label>
            <select id="program" name="program" class="sa-input pr-8">
                <option value="">Any</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected(($filters['program'] ?? '') === $program->id)>{{ $program->name }}</option>
                @endforeach
            </select>
        </div>
    </x-super-admin.filters>

    <x-super-admin.card class="mt-6">
        @if ($referrals->isEmpty())
            <x-super-admin.empty icon="user">No referrals match.</x-super-admin.empty>
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
                            <tr class="hover:bg-zinc-50/70">
                                <td><a href="{{ route('super-admin.referrals.referrals.show', $referral) }}" class="sa-link">{{ $referral->referredUser?->email ?? 'Deleted user' }}</a></td>
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

    <div class="mt-4">{{ $referrals->links() }}</div>
</x-super-admin.layout>
