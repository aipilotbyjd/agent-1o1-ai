<x-super-admin.layout :title="'Run '.\Illuminate\Support\Str::limit($run->id, 8, '')">
    <a href="{{ route('super-admin.runs.index') }}" class="text-sm text-zinc-500 hover:text-zinc-900">&larr; All runs</a>

    <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-super-admin.card title="Run">
            <dl class="space-y-3 px-5 py-4 text-sm">
                <div><dt class="text-zinc-500">Status</dt><dd><x-super-admin.status-badge :status="$run->status" /></dd></div>
                <div><dt class="text-zinc-500">ID</dt><dd class="font-mono text-xs break-all">{{ $run->id }}</dd></div>
                <div>
                    <dt class="text-zinc-500">Workspace</dt>
                    <dd>
                        @if ($run->workspace)
                            <a href="{{ route('super-admin.workspaces.show', $run->workspace) }}" class="hover:underline">{{ $run->workspace->name }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div><dt class="text-zinc-500">What ran</dt><dd>{{ $run->workflow?->name ?? class_basename($run->runnable_type) }}</dd></div>
                <div><dt class="text-zinc-500">Trigger</dt><dd>{{ str_replace('_', ' ', $run->trigger_type) }}</dd></div>
                <div>
                    <dt class="text-zinc-500">Started by</dt>
                    <dd>
                        @if ($run->triggeredBy)
                            <a href="{{ route('super-admin.users.show', $run->triggeredBy) }}" class="hover:underline">{{ $run->triggeredBy->email }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div><dt class="text-zinc-500">Created</dt><dd>{{ $run->created_at?->toDayDateTimeString() }}</dd></div>
                <div><dt class="text-zinc-500">Started</dt><dd>{{ $run->started_at?->toDayDateTimeString() ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">Finished</dt><dd>{{ $run->finished_at?->toDayDateTimeString() ?? '—' }}</dd></div>
                @if ($run->parentRun)
                    <div><dt class="text-zinc-500">Parent run</dt><dd><a href="{{ route('super-admin.runs.show', $run->parentRun) }}" class="font-mono text-xs hover:underline">{{ $run->parentRun->id }}</a></dd></div>
                @endif
                @if ($run->retriedFrom)
                    <div><dt class="text-zinc-500">Retry of</dt><dd><a href="{{ route('super-admin.runs.show', $run->retriedFrom) }}" class="font-mono text-xs hover:underline">{{ $run->retriedFrom->id }}</a></dd></div>
                @endif
                @if ($run->child_runs_count)
                    <div><dt class="text-zinc-500">Child runs</dt><dd>{{ $run->child_runs_count }}</dd></div>
                @endif
            </dl>
        </x-super-admin.card>

        <div class="space-y-6 lg:col-span-2">
            @if ($run->error)
                <x-super-admin.card title="Error">
                    <pre class="overflow-x-auto px-5 py-4 text-xs whitespace-pre-wrap text-red-700">{{ $run->error }}</pre>
                </x-super-admin.card>
            @endif

            <x-super-admin.card title="Steps ({{ $run->nodeRuns->count() }})">
                @if ($run->nodeRuns->isEmpty())
                    <p class="px-5 py-6 text-sm text-zinc-500">No steps ran.</p>
                @else
                    <ul class="divide-y divide-zinc-100 text-sm">
                        @foreach ($run->nodeRuns as $nodeRun)
                            <li class="px-5 py-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <span class="font-medium">{{ $nodeRun->key }}</span>
                                        <span class="ml-2 font-mono text-xs text-zinc-500">{{ $nodeRun->type }}</span>
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
                                    <pre class="mt-2 overflow-x-auto rounded bg-red-50 p-2 text-xs whitespace-pre-wrap text-red-700">{{ $nodeRun->error }}</pre>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-super-admin.card>

            <p class="text-xs text-zinc-500">Inputs and outputs are customer data and aren't shown here.</p>
        </div>
    </div>
</x-super-admin.layout>
