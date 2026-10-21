@if ($entries->isEmpty())
    <x-super-admin.empty icon="shield">{{ $empty ?? 'No entries.' }}</x-super-admin.empty>
@else
    <div class="overflow-x-auto">
        <table class="sa-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Who</th>
                    <th>Action</th>
                    <th>Subject</th>
                    <th>IP</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr class="align-top">
                        <td class="whitespace-nowrap text-zinc-500" title="{{ $entry->created_at }}">{{ $entry->created_at?->diffForHumans() }}</td>
                        <td>
                            <div class="flex items-center gap-2.5">
                                <x-super-admin.avatar :name="$entry->superAdmin?->name ?: ($entry->superAdmin?->email ?? 'System')" size="size-7" />
                                @if ($entry->superAdmin)
                                    <span class="text-zinc-900">{{ $entry->superAdmin->email }}</span>
                                @else
                                    <span class="text-zinc-500">System / CLI</span>
                                @endif
                            </div>
                        </td>
                        <td><code class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs text-zinc-700">{{ $entry->action }}</code></td>
                        <td class="text-xs">
                            @if ($entry->subject_type)
                                <span class="text-zinc-700">{{ $entry->subject_type }}</span>
                                <span class="block font-mono text-zinc-400" title="{{ $entry->subject_id }}">{{ \Illuminate\Support\Str::limit((string) $entry->subject_id, 8, '…') }}</span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="font-mono text-xs text-zinc-500">{{ $entry->ip_address ?? '—' }}</td>
                        <td class="text-xs">
                            @if ($entry->before || $entry->after)
                                <details class="group">
                                    <summary class="inline-flex cursor-pointer list-none items-center gap-1 font-medium text-zinc-500 hover:text-zinc-900">
                                        <x-super-admin.icon name="chevron-right" class="size-3.5 transition group-open:rotate-90" />
                                        View
                                    </summary>
                                    @if ($entry->before)
                                        <p class="mt-2 font-medium text-zinc-700">Before</p>
                                        <pre class="mt-1 max-w-md overflow-x-auto rounded-lg bg-zinc-950 p-3 text-zinc-200">{{ json_encode($entry->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                    @endif
                                    @if ($entry->after)
                                        <p class="mt-2 font-medium text-zinc-700">After</p>
                                        <pre class="mt-1 max-w-md overflow-x-auto rounded-lg bg-zinc-950 p-3 text-zinc-200">{{ json_encode($entry->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                    @endif
                                </details>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
