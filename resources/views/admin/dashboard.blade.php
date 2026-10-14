<x-admin.layout title="Dashboard">
    @php
        $tiles = [
            ['label' => 'Users', 'value' => $stats['users_total'], 'hint' => '+'.number_format($stats['users_new_7d']).' in the last 7 days'],
            ['label' => 'Workspaces', 'value' => $stats['workspaces_total'], 'hint' => null],
            ['label' => 'Active subscriptions', 'value' => $stats['active_subscriptions'], 'hint' => 'Active or trialing'],
            ['label' => 'Runs today', 'value' => $stats['runs_today'], 'hint' => number_format($stats['runs_failed_today']).' failed'],
        ];
    @endphp

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($tiles as $tile)
            <div class="rounded-xl border border-zinc-200 bg-white p-5">
                <p class="text-sm text-zinc-500">{{ $tile['label'] }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($tile['value']) }}</p>
                @if ($tile['hint'])
                    <p class="mt-1 text-xs text-zinc-500">{{ $tile['hint'] }}</p>
                @endif
            </div>
        @endforeach
    </section>

    <p class="mt-2 text-xs text-zinc-400">Updated {{ \Illuminate\Support\Carbon::parse($stats['generated_at'])->diffForHumans() }}</p>

    <section class="mt-8 rounded-xl border border-zinc-200 bg-white">
        <h2 class="border-b border-zinc-200 px-5 py-3 text-sm font-semibold">Recent admin activity</h2>

        @if ($recentActivity->isEmpty())
            <p class="px-5 py-6 text-sm text-zinc-500">No admin activity yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-zinc-500 uppercase">
                        <tr>
                            <th class="px-5 py-2 font-medium">When</th>
                            <th class="px-5 py-2 font-medium">Who</th>
                            <th class="px-5 py-2 font-medium">Action</th>
                            <th class="px-5 py-2 font-medium">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($recentActivity as $entry)
                            <tr>
                                <td class="px-5 py-2 whitespace-nowrap text-zinc-500" title="{{ $entry->created_at }}">{{ $entry->created_at?->diffForHumans() }}</td>
                                <td class="px-5 py-2">{{ $entry->panelAdmin?->email ?? $entry->admin?->email ?? 'System / CLI' }}</td>
                                <td class="px-5 py-2 font-mono text-xs">{{ $entry->action }}</td>
                                <td class="px-5 py-2 font-mono text-xs text-zinc-500">{{ $entry->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-admin.layout>
