<x-super-admin.layout title="Dashboard" :description="'Platform health over the last '.$days.' days · updated '.\Illuminate\Support\Carbon::parse($stats['generated_at'])->diffForHumans()">
    <x-slot:actions>
        <nav class="inline-flex rounded-lg bg-white p-1 shadow-xs ring-1 ring-zinc-200" aria-label="Time window">
            @foreach (collect($windows)->sort() as $window)
                <a href="{{ route('super-admin.dashboard', ['days' => $window]) }}" @class([
                    'rounded-md px-3 py-1 text-sm font-medium transition',
                    'bg-zinc-900 text-white shadow-sm' => $window === $days,
                    'text-zinc-500 hover:text-zinc-900' => $window !== $days,
                ]) @if ($window === $days) aria-current="true" @endif>{{ $window }}d</a>
            @endforeach
        </nav>
    </x-slot:actions>

    @php
        $geometryRules = [];
        $geometry = function (string $property, int|float $value, string $unit = '') use (&$geometryRules): string {
            $class = 'sa-geo-'.count($geometryRules);
            $geometryRules[] = '.'.$class.'{'.$property.':'.round($value, 3).$unit.'}';

            return $class;
        };
        $change = fn (int $current, int $previous): ?float => $previous === 0 ? null : ($current - $previous) / $previous;
        $duration = fn (?int $ms): string => $ms === null ? '—' : \Carbon\CarbonInterval::milliseconds($ms)->cascade()->forHumans(['short' => true, 'parts' => 2]);
        $runs = $stats['runs'];
        $inFlightTotal = array_sum($stats['in_flight']);

        $tiles = [
            [
                'label' => 'Users', 'icon' => 'user', 'url' => route('super-admin.users.index'),
                'value' => number_format($stats['users']['total']),
                'note' => '+'.number_format($stats['users']['new']).' new',
                'change' => $change($stats['users']['new'], $stats['users']['new_previous']),
            ],
            [
                'label' => 'Workspaces', 'icon' => 'workspace', 'url' => route('super-admin.workspaces.index'),
                'value' => number_format($stats['workspaces']['total']),
                'note' => '+'.number_format($stats['workspaces']['new']).' new',
                'change' => $change($stats['workspaces']['new'], $stats['workspaces']['new_previous']),
            ],
            [
                'label' => 'Runs', 'icon' => 'bolt', 'url' => route('super-admin.runs.index', ['from' => \Illuminate\Support\Carbon::parse($stats['window']['starts_at'])->toDateString()]),
                'value' => number_format($runs['total']),
                'note' => 'avg '.$duration($runs['avg_duration_ms']),
                'change' => $change($runs['total'], $runs['previous_total']),
            ],
            [
                'label' => 'Credits used', 'icon' => 'card', 'url' => null,
                'value' => number_format($stats['credits']['used']),
                'note' => number_format($stats['subscriptions']['active'] + $stats['subscriptions']['trialing']).' paying '.str('workspace')->plural($stats['subscriptions']['active'] + $stats['subscriptions']['trialing']),
                'change' => $change($stats['credits']['used'], $stats['credits']['used_previous']),
            ],
        ];

        $series = $stats['run_series'];
        $peak = max(1, max(array_column($series, 'total')));
        $magnitude = 10 ** floor(log10($peak));
        $axisMax = collect([1, 1.2, 1.6, 2, 2.4, 4, 5, 8, 10])->map(fn ($step) => $step * $magnitude)->first(fn ($candidate) => $candidate >= $peak);
        $axisMax = (int) max(1, ceil($axisMax));
        $labelEvery = max(1, (int) ceil(count($series) / 6));

        $signups = $stats['signup_series'];
        $signupPeak = max(1, max(array_column($signups, 'total')));

        $finished = $runs['completed'] + $runs['failed'] + $runs['cancelled'];
        $outcomes = [
            ['Completed', $runs['completed'], 'bg-good', 'check-circle'],
            ['Failed', $runs['failed'], 'bg-critical', 'warning'],
            ['Cancelled', $runs['cancelled'], 'bg-zinc-300', 'close'],
        ];
    @endphp

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($tiles as $tile)
            <div class="group relative rounded-2xl bg-white p-5 shadow-xs ring-1 ring-zinc-200/80 transition hover:shadow-md hover:ring-zinc-300">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-zinc-500">
                        @if ($tile['url'])
                            <a href="{{ $tile['url'] }}" class="after:absolute after:inset-0">{{ $tile['label'] }}</a>
                        @else
                            {{ $tile['label'] }}
                        @endif
                    </p>
                    <span class="flex size-9 items-center justify-center rounded-xl bg-zinc-50 text-zinc-500 ring-1 ring-zinc-200/70 transition group-hover:bg-indigo-50 group-hover:text-indigo-600 group-hover:ring-indigo-100">
                        <x-super-admin.icon :name="$tile['icon']" class="size-[18px]" />
                    </span>
                </div>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-zinc-950 tabular-nums">{{ $tile['value'] }}</p>
                <div class="mt-2 flex items-center gap-2 text-xs">
                    @if ($tile['change'] !== null)
                        <span @class([
                            'inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 font-semibold tabular-nums',
                            'bg-emerald-50 text-emerald-700' => $tile['change'] >= 0,
                            'bg-red-50 text-red-700' => $tile['change'] < 0,
                        ])>
                            <x-super-admin.icon :name="$tile['change'] >= 0 ? 'arrow-up' : 'arrow-down'" class="size-3" />
                            {{ number_format(abs($tile['change']) * 100, $tile['change'] != 0 && abs($tile['change']) < 0.1 ? 1 : 0) }}%
                        </span>
                    @endif
                    <span class="text-zinc-500">{{ $tile['note'] }}</span>
                </div>
            </div>
        @endforeach
    </section>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-super-admin.card title="Run volume" description="Top-level runs per day, node tests excluded" class="xl:col-span-2">
            <x-slot:actions>
                <span class="inline-flex items-center gap-1.5 text-zinc-600"><span class="size-2.5 rounded-sm bg-good"></span>Completed</span>
                <span class="inline-flex items-center gap-1.5 text-zinc-600"><span class="size-2.5 rounded-sm bg-critical"></span>Failed</span>
                <span class="inline-flex items-center gap-1.5 text-zinc-600"><span class="size-2.5 rounded-sm bg-zinc-300"></span>Other</span>
            </x-slot:actions>

            <div class="px-5 pt-6 pb-4">
                <div class="relative flex h-64 gap-3">
                    <div class="relative w-8 text-right text-[11px] text-zinc-400 tabular-nums" aria-hidden="true">
                        @foreach (['top-0' => 1, 'top-1/4' => 0.75, 'top-1/2' => 0.5, 'top-3/4' => 0.25, 'top-full' => 0] as $position => $fraction)
                            <span class="{{ $position }} absolute right-0 -translate-y-1/2">{{ number_format($axisMax * $fraction, $axisMax < 4 ? 1 : 0) }}</span>
                        @endforeach
                    </div>
                    <div class="relative flex-1">
                        <div class="pointer-events-none absolute inset-0 flex flex-col justify-between" aria-hidden="true">
                            @foreach (range(0, 4) as $line)
                                <div @class(['border-t', 'border-zinc-200' => $line === 4, 'border-dashed border-zinc-100' => $line !== 4])></div>
                            @endforeach
                        </div>
                        <div class="relative flex h-full items-end gap-[2px]">
                            @foreach ($series as $day)
                                @php
                                    $other = $day['total'] - $day['completed'] - $day['failed'];
                                    $date = \Illuminate\Support\Carbon::parse($day['date']);
                                @endphp
                                <div class="group/bar relative flex h-full flex-1 flex-col justify-end">
                                    <div class="flex w-full flex-col-reverse gap-[2px] overflow-hidden rounded-t-[4px] transition group-hover/bar:opacity-80 {{ $geometry('height', $day['total'] / $axisMax * 100, '%') }}">
                                        @foreach ([[$day['completed'], 'bg-good'], [$day['failed'], 'bg-critical'], [$other, 'bg-zinc-300']] as [$count, $color])
                                            @if ($count > 0)
                                                <div class="{{ $color }} w-full {{ $geometry('flex-grow', $count) }}"></div>
                                            @endif
                                        @endforeach
                                    </div>
                                    <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden w-36 -translate-x-1/2 rounded-lg bg-zinc-900 px-3 py-2 text-xs text-zinc-300 shadow-lg group-hover/bar:block">
                                        <p class="font-semibold text-white">{{ $date->format('D, M j') }}</p>
                                        <p class="mt-1 flex justify-between"><span>Total</span><span class="text-white tabular-nums">{{ number_format($day['total']) }}</span></p>
                                        <p class="flex justify-between"><span class="flex items-center gap-1"><span class="size-2 rounded-sm bg-good"></span>Completed</span><span class="tabular-nums">{{ number_format($day['completed']) }}</span></p>
                                        <p class="flex justify-between"><span class="flex items-center gap-1"><span class="size-2 rounded-sm bg-critical"></span>Failed</span><span class="tabular-nums">{{ number_format($day['failed']) }}</span></p>
                                        @if ($other > 0)
                                            <p class="flex justify-between"><span class="flex items-center gap-1"><span class="size-2 rounded-sm bg-zinc-300"></span>Other</span><span class="tabular-nums">{{ number_format($other) }}</span></p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="mt-2 ml-11 flex gap-[2px] text-[11px] text-zinc-400">
                    @foreach ($series as $index => $day)
                        <span class="relative flex-1">
                            @if ($index % $labelEvery === 0)
                                <span class="absolute left-0 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('M j') }}</span>
                            @endif
                        </span>
                    @endforeach
                </div>
                <div class="h-4"></div>
            </div>
        </x-super-admin.card>

        <div class="flex flex-col gap-6">
            <x-super-admin.card title="Success rate" :description="number_format($finished).' finished '.str('run')->plural($finished)">
                <div class="px-5 py-5">
                    <p class="text-4xl font-semibold tracking-tight tabular-nums">
                        {{ $runs['success_rate'] === null ? '—' : number_format($runs['success_rate'] * 100, 1).'%' }}
                    </p>
                    @if ($finished > 0)
                        <div class="mt-4 flex h-2.5 gap-[2px] overflow-hidden rounded-full" role="img" aria-label="Run outcomes">
                            @foreach ($outcomes as [$label, $count, $color])
                                @if ($count > 0)
                                    <div class="{{ $color }} {{ $geometry('flex-grow', $count) }}" title="{{ $label }}: {{ number_format($count) }}"></div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    <dl class="mt-4 space-y-2 text-sm">
                        @foreach ($outcomes as [$label, $count, $color])
                            <div class="flex items-center justify-between">
                                <dt class="flex items-center gap-2 text-zinc-600"><span class="size-2.5 rounded-sm {{ $color }}"></span>{{ $label }}</dt>
                                <dd class="font-medium tabular-nums">{{ number_format($count) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </x-super-admin.card>

            <x-super-admin.card title="In flight now">
                <x-slot:actions>
                    @if ($inFlightTotal > 0)
                        <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-amber-400 opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-amber-500"></span></span>
                    @endif
                    <span class="font-semibold text-zinc-900 tabular-nums">{{ number_format($inFlightTotal) }}</span>
                </x-slot:actions>
                <ul class="divide-y divide-zinc-100 text-sm">
                    @foreach ($stats['in_flight'] as $status => $count)
                        <li>
                            <a href="{{ route('super-admin.runs.index', ['status' => $status]) }}" class="flex items-center justify-between px-5 py-2.5 transition hover:bg-zinc-50">
                                <span class="text-zinc-600 capitalize">{{ str_replace('_', ' ', $status) }}</span>
                                <span @class(['tabular-nums', 'font-semibold text-zinc-900' => $count > 0, 'text-zinc-400' => $count === 0])>{{ number_format($count) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-super-admin.card>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-super-admin.card title="Recent runs" class="xl:col-span-2">
            <x-slot:actions>
                <a href="{{ route('super-admin.runs.index') }}" class="inline-flex items-center gap-0.5 font-medium text-zinc-500 hover:text-zinc-900">View all<x-super-admin.icon name="chevron-right" class="size-3.5" /></a>
            </x-slot:actions>
            @include('super-admin.runs.partials.table', ['runs' => $recentRuns, 'empty' => 'No runs yet.'])
        </x-super-admin.card>

        <x-super-admin.card title="New signups" :description="number_format($stats['users']['new']).' in the last '.$days.' days'">
            <div class="flex h-14 items-end gap-[2px] px-5 pt-4" role="img" aria-label="Signups per day">
                @foreach ($signups as $day)
                    <div class="group/s relative flex h-full flex-1 items-end">
                        <div @class(['w-full rounded-t-[3px]', 'bg-indigo-500' => $day['total'] > 0, 'bg-zinc-100' => $day['total'] === 0, $geometry('height', $day['total'] > 0 ? max(8, $day['total'] / $signupPeak * 100) : 6, '%')])></div>
                        <span class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 rounded-md bg-zinc-900 px-2 py-1 text-[11px] whitespace-nowrap text-white group-hover/s:block">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('M j') }} · {{ $day['total'] }}</span>
                    </div>
                @endforeach
            </div>
            <ul class="mt-4 divide-y divide-zinc-100 border-t border-zinc-100">
                @forelse ($recentUsers as $user)
                    <li>
                        <a href="{{ route('super-admin.users.show', $user) }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-zinc-50">
                            <x-super-admin.avatar :name="$user->name ?: $user->email" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-zinc-900">{{ $user->name }}</p>
                                <p class="truncate text-xs text-zinc-500">{{ $user->email }}</p>
                            </div>
                            <span class="shrink-0 text-xs text-zinc-400" title="{{ $user->created_at }}">{{ $user->created_at?->diffForHumans(short: true) }}</span>
                        </a>
                    </li>
                @empty
                    <li><x-super-admin.empty icon="user">No users yet.</x-super-admin.empty></li>
                @endforelse
            </ul>
        </x-super-admin.card>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-super-admin.card title="Busiest workspaces" :description="'By runs in the last '.$days.' days'">
            @if ($stats['top_workspaces'] === [])
                <x-super-admin.empty icon="workspace">No runs in this window.</x-super-admin.empty>
            @else
                @php $topPeak = max(array_column($stats['top_workspaces'], 'runs')); @endphp
                <ul class="space-y-4 px-5 py-5">
                    @foreach ($stats['top_workspaces'] as $workspace)
                        <li>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <a href="{{ route('super-admin.workspaces.show', $workspace['id']) }}" class="truncate font-medium text-zinc-900 hover:underline">{{ $workspace['name'] }}</a>
                                <span class="shrink-0 text-zinc-500 tabular-nums">{{ number_format($workspace['runs']) }} <span class="text-zinc-400">· {{ number_format($workspace['failed']) }} failed</span></span>
                            </div>
                            <div class="mt-2 h-1.5 rounded-full bg-zinc-100">
                                <div class="h-full rounded-full bg-indigo-500 {{ $geometry('width', $workspace['runs'] / $topPeak * 100, '%') }}"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-super-admin.card>

        <x-super-admin.card title="Recent super admin activity" class="xl:col-span-2">
            <x-slot:actions>
                <a href="{{ route('super-admin.audit-log.index') }}" class="inline-flex items-center gap-0.5 font-medium text-zinc-500 hover:text-zinc-900">Full audit log<x-super-admin.icon name="chevron-right" class="size-3.5" /></a>
            </x-slot:actions>
            @if ($recentActivity->isEmpty())
                <x-super-admin.empty icon="shield">No super admin activity yet.</x-super-admin.empty>
            @else
                <ol class="px-5 py-4">
                    @foreach ($recentActivity as $entry)
                        <li class="relative flex gap-3 pb-4 last:pb-0">
                            @unless ($loop->last)
                                <span class="absolute top-8 bottom-0 left-4 w-px bg-zinc-200" aria-hidden="true"></span>
                            @endunless
                            <x-super-admin.avatar :name="$entry->superAdmin?->name ?: ($entry->superAdmin?->email ?? 'System')" class="relative ring-4 ring-white" />
                            <div class="min-w-0 flex-1 pt-1">
                                <p class="text-sm text-zinc-600">
                                    <span class="font-medium text-zinc-900">{{ $entry->superAdmin?->email ?? 'System / CLI' }}</span>
                                    <code class="ml-1 rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs text-zinc-700">{{ $entry->action }}</code>
                                </p>
                                <p class="mt-0.5 text-xs text-zinc-400">
                                    <span title="{{ $entry->created_at }}">{{ $entry->created_at?->diffForHumans() }}</span>
                                    @if ($entry->ip_address)
                                        · <span class="font-mono">{{ $entry->ip_address }}</span>
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-super-admin.card>
    </div>
    @push('styles')
        <style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{{ implode('', $geometryRules) }}</style>
    @endpush
</x-super-admin.layout>
