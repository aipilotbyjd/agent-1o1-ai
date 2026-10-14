@if ($runs->isEmpty())
    <x-super-admin.empty icon="bolt">{{ $empty ?? 'No runs.' }}</x-super-admin.empty>
@else
    <div class="overflow-x-auto">
        <table class="sa-table">
            <thead>
                <tr>
                    <th>Run</th>
                    @if ($showWorkspace ?? true)
                        <th>Workspace</th>
                    @endif
                    <th>Status</th>
                    <th>Trigger</th>
                    <th class="text-right">Duration</th>
                    <th class="text-right">Started</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($runs as $run)
                    <tr class="group relative transition hover:bg-zinc-50/80">
                        <td>
                            <a href="{{ route('super-admin.runs.show', $run) }}" class="font-medium text-zinc-900 after:absolute after:inset-0">{{ $run->workflow?->name ?? class_basename($run->runnable_type) }}</a>
                            <p class="font-mono text-xs text-zinc-400">{{ \Illuminate\Support\Str::limit($run->id, 8, '') }}</p>
                        </td>
                        @if ($showWorkspace ?? true)
                            <td class="text-zinc-600">
                                @if ($run->workspace)
                                    <a href="{{ route('super-admin.workspaces.show', $run->workspace) }}" class="relative z-10 hover:text-zinc-900 hover:underline">{{ $run->workspace->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                        @endif
                        <td><x-super-admin.status-badge :status="$run->status" /></td>
                        <td><span class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-xs font-medium text-zinc-600">{{ str_replace('_', ' ', $run->trigger_type) }}</span></td>
                        <td class="text-right text-zinc-600 tabular-nums">
                            {{ $run->started_at && $run->finished_at ? \Carbon\CarbonInterval::milliseconds($run->started_at->diffInMilliseconds($run->finished_at))->cascade()->forHumans(['short' => true, 'parts' => 2]) : '—' }}
                        </td>
                        <td class="text-right whitespace-nowrap text-zinc-500" title="{{ $run->created_at }}">{{ $run->created_at?->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
