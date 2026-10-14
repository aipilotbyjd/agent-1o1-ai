<x-admin.layout title="Dashboard">
    @php
        $tiles = [
            ['label' => 'Users', 'url' => route('panel.users.index'), 'value' => $stats['users_total'], 'hint' => '+'.number_format($stats['users_new_7d']).' in the last 7 days'],
            ['label' => 'Workspaces', 'url' => route('panel.workspaces.index'), 'value' => $stats['workspaces_total'], 'hint' => null],
            ['label' => 'Active subscriptions', 'url' => null, 'value' => $stats['active_subscriptions'], 'hint' => 'Active or trialing'],
            ['label' => 'Runs today', 'url' => route('panel.runs.index', ['from' => now()->toDateString()]), 'value' => $stats['runs_today'], 'hint' => number_format($stats['runs_failed_today']).' failed'],
        ];
    @endphp

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($tiles as $tile)
            <div class="relative rounded-xl border border-zinc-200 bg-white p-5">
                <p class="text-sm text-zinc-500">
                    @if ($tile['url'])
                        <a href="{{ $tile['url'] }}" class="after:absolute after:inset-0 hover:text-zinc-900">{{ $tile['label'] }}</a>
                    @else
                        {{ $tile['label'] }}
                    @endif
                </p>
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

        @include('admin.audit-log.partials.entries', ['entries' => $recentActivity, 'empty' => 'No admin activity yet.'])
        <p class="border-t border-zinc-100 px-5 py-2 text-right text-xs"><a href="{{ route('panel.audit-log.index') }}" class="text-zinc-500 hover:text-zinc-900">Full audit log &rarr;</a></p>
    </section>
</x-admin.layout>
