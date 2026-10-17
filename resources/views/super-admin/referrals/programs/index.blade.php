<x-super-admin.layout title="Referral programs" description="New signups join the default program unless their code points at another one">
    <x-slot:actions>
        <a href="{{ route('super-admin.referrals.programs.create') }}" class="sa-btn-primary">New program</a>
    </x-slot:actions>

    <x-super-admin.card>
        @if ($programs->isEmpty())
            <x-super-admin.empty icon="megaphone">No referral programs yet.</x-super-admin.empty>
        @else
            <div class="overflow-x-auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Program</th>
                            <th>Status</th>
                            <th>Approval</th>
                            <th class="text-right">Rules</th>
                            <th class="text-right">Referrals</th>
                            <th>Runs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($programs as $program)
                            <tr>
                                <td>
                                    <a href="{{ route('super-admin.referrals.programs.show', $program) }}" class="sa-row-link">{{ $program->name }}</a>
                                    @if ($program->is_default)
                                        <span class="ml-1 rounded-md bg-indigo-50 px-1.5 py-0.5 text-[11px] font-semibold text-indigo-700">Default</span>
                                    @endif
                                    <p class="font-mono text-xs text-zinc-400">{{ $program->slug }}</p>
                                </td>
                                <td><x-super-admin.status-badge :status="$program->isLive() ? 'active' : 'off'" /></td>
                                <td class="capitalize">{{ $program->approval_mode?->value }}</td>
                                <td class="text-right tabular-nums">{{ $program->rules_count }}</td>
                                <td class="text-right tabular-nums">{{ number_format($program->referrals_count) }}</td>
                                <td class="text-xs whitespace-nowrap text-zinc-500">
                                    {{ $program->starts_at?->toFormattedDateString() ?? 'Always' }} → {{ $program->ends_at?->toFormattedDateString() ?? 'no end' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-super-admin.card>
</x-super-admin.layout>
