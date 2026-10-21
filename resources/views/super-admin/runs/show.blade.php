<x-super-admin.layout :title="$run->workflow?->name ?? class_basename($run->runnable_type)" :description="'Run '.$run->id" :back="route('super-admin.runs.index')" back-label="All runs">
    <x-slot:actions>
        <x-super-admin.status-badge :status="$run->status" class="px-2.5 py-1 text-sm" />
    </x-slot:actions>

    @php
        $steps = $run->nodeRuns;
        $failedSteps = $steps->filter(fn ($nodeRun) => ($nodeRun->status instanceof \BackedEnum ? $nodeRun->status->value : $nodeRun->status) === 'failed')->count();
    @endphp

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-super-admin.card title="Run">
            <dl class="sa-dl">
                <div><dt>Status</dt><dd><x-super-admin.status-badge :status="$run->status" /></dd></div>
                <div><dt>ID</dt><dd class="font-mono text-xs break-all">{{ $run->id }}</dd></div>
                <div>
                    <dt>Workspace</dt>
                    <dd>
                        @if ($run->workspace)
                            <a href="{{ route('super-admin.workspaces.show', $run->workspace) }}" class="sa-link">{{ $run->workspace->name }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div><dt>What ran</dt><dd>{{ $run->workflow?->name ?? class_basename($run->runnable_type) }}</dd></div>
                <div><dt>Trigger</dt><dd><span class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-xs font-medium text-zinc-600">{{ str_replace('_', ' ', $run->trigger_type) }}</span></dd></div>
                <div>
                    <dt>Started by</dt>
                    <dd>
                        @if ($run->triggeredBy)
                            <a href="{{ route('super-admin.users.show', $run->triggeredBy) }}" class="sa-link">{{ $run->triggeredBy->email }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div><dt>Created</dt><dd>{{ $run->created_at?->toDayDateTimeString() }}</dd></div>
                <div><dt>Started</dt><dd>{{ $run->started_at?->toDayDateTimeString() ?? '—' }}</dd></div>
                <div><dt>Finished</dt><dd>{{ $run->finished_at?->toDayDateTimeString() ?? '—' }}</dd></div>
                @if ($run->started_at && $run->finished_at)
                    <div><dt>Duration</dt><dd class="tabular-nums">{{ \Carbon\CarbonInterval::milliseconds($run->started_at->diffInMilliseconds($run->finished_at))->cascade()->forHumans(['short' => true, 'parts' => 2]) }}</dd></div>
                @endif
                @if ($run->parentRun)
                    <div><dt>Parent run</dt><dd><a href="{{ route('super-admin.runs.show', $run->parentRun) }}" class="sa-link font-mono text-xs">{{ $run->parentRun->id }}</a></dd></div>
                @endif
                @if ($run->retriedFrom)
                    <div><dt>Retry of</dt><dd><a href="{{ route('super-admin.runs.show', $run->retriedFrom) }}" class="sa-link font-mono text-xs">{{ $run->retriedFrom->id }}</a></dd></div>
                @endif
                @if ($run->child_runs_count)
                    <div><dt>Child runs</dt><dd>{{ $run->child_runs_count }}</dd></div>
                @endif
            </dl>
        </x-super-admin.card>

        <div class="space-y-6 lg:col-span-2">
            @if ($run->error)
                <section class="overflow-hidden rounded-2xl bg-red-50 ring-1 ring-red-600/15">
                    <header class="flex items-center gap-2 border-b border-red-600/10 px-5 py-3 text-sm font-semibold text-red-800">
                        <x-super-admin.icon name="warning" class="size-4.5" />
                        Error
                    </header>
                    <pre class="overflow-x-auto px-5 py-4 font-mono text-xs whitespace-pre-wrap text-red-800">{{ $run->error }}</pre>
                </section>
            @endif

            <x-super-admin.card title="Steps" :description="$steps->count().' '.str('step')->plural($steps->count()).($failedSteps ? ' · '.$failedSteps.' failed' : '')">
                @if ($steps->isEmpty())
                    <x-super-admin.empty icon="bolt">No steps ran.</x-super-admin.empty>
                @else
                    <ol class="px-5 py-4">
                        @foreach ($steps as $nodeRun)
                            @php $stepStatus = $nodeRun->status instanceof \BackedEnum ? $nodeRun->status->value : (string) $nodeRun->status; @endphp
                            <li class="relative flex gap-4 pb-5 last:pb-0">
                                @unless ($loop->last)
                                    <span class="absolute top-7 bottom-0 left-3 w-px bg-zinc-200" aria-hidden="true"></span>
                                @endunless
                                <span @class([
                                    'relative flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold ring-4 ring-white',
                                    'bg-emerald-100 text-emerald-700' => $stepStatus === 'completed',
                                    'bg-red-100 text-red-700' => $stepStatus === 'failed',
                                    'bg-amber-100 text-amber-800' => in_array($stepStatus, ['running', 'pending', 'awaiting_approval', 'awaiting_callback'], true),
                                    'bg-zinc-100 text-zinc-500' => ! in_array($stepStatus, ['completed', 'failed', 'running', 'pending', 'awaiting_approval', 'awaiting_callback'], true),
                                ])>{{ $loop->iteration }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <span class="font-medium text-zinc-900">{{ $nodeRun->key }}</span>
                                            <span class="ml-2 rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] text-zinc-500">{{ $nodeRun->type }}</span>
                                        </div>
                                        <div class="flex items-center gap-3 text-xs text-zinc-500">
                                            @if ($nodeRun->max_attempts > 1)
                                                <span>attempt {{ $nodeRun->attempt }}/{{ $nodeRun->max_attempts }}</span>
                                            @endif
                                            @if ($nodeRun->started_at && $nodeRun->finished_at)
                                                <span class="tabular-nums">{{ number_format($nodeRun->started_at->diffInMilliseconds($nodeRun->finished_at)) }} ms</span>
                                            @endif
                                            <x-super-admin.status-badge :status="$nodeRun->status" />
                                        </div>
                                    </div>
                                    @if ($nodeRun->error)
                                        <pre class="mt-2 overflow-x-auto rounded-lg bg-red-50 p-3 font-mono text-xs whitespace-pre-wrap text-red-800 ring-1 ring-red-600/10">{{ $nodeRun->error }}</pre>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-super-admin.card>

            <p class="flex items-center gap-1.5 text-xs text-zinc-500">
                <x-super-admin.icon name="lock" class="size-3.5" />
                Inputs and outputs are customer data and aren't shown here.
            </p>
        </div>
    </div>
</x-super-admin.layout>
