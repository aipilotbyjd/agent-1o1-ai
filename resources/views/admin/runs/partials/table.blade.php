@if ($runs->isEmpty())
    <p class="px-5 py-6 text-sm text-zinc-500">{{ $empty ?? 'No runs.' }}</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="text-xs text-zinc-500 uppercase">
                <tr>
                    <th class="px-5 py-2 font-medium">Run</th>
                    @if ($showWorkspace ?? true)
                        <th class="px-5 py-2 font-medium">Workspace</th>
                    @endif
                    <th class="px-5 py-2 font-medium">Status</th>
                    <th class="px-5 py-2 font-medium">Trigger</th>
                    <th class="px-5 py-2 font-medium">Duration</th>
                    <th class="px-5 py-2 font-medium">Started</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @foreach ($runs as $run)
                    <tr class="hover:bg-zinc-50">
                        <td class="px-5 py-2">
                            <a href="{{ route('panel.runs.show', $run) }}" class="font-medium hover:underline">{{ $run->workflow?->name ?? class_basename($run->runnable_type) }}</a>
                            <p class="font-mono text-xs text-zinc-500">{{ \Illuminate\Support\Str::limit($run->id, 8, '') }}</p>
                        </td>
                        @if ($showWorkspace ?? true)
                            <td class="px-5 py-2">
                                @if ($run->workspace)
                                    <a href="{{ route('panel.workspaces.show', $run->workspace) }}" class="hover:underline">{{ $run->workspace->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                        @endif
                        <td class="px-5 py-2"><x-admin.status-badge :status="$run->status" /></td>
                        <td class="px-5 py-2 text-zinc-500">{{ str_replace('_', ' ', $run->trigger_type) }}</td>
                        <td class="px-5 py-2 text-zinc-500 tabular-nums">
                            {{ $run->started_at && $run->finished_at ? \Carbon\CarbonInterval::milliseconds($run->started_at->diffInMilliseconds($run->finished_at))->cascade()->forHumans(['short' => true, 'parts' => 2]) : '—' }}
                        </td>
                        <td class="px-5 py-2 whitespace-nowrap text-zinc-500" title="{{ $run->created_at }}">{{ $run->created_at?->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
