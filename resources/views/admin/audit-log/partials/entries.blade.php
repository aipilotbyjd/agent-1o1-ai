@if ($entries->isEmpty())
    <p class="px-5 py-6 text-sm text-zinc-500">{{ $empty ?? 'No entries.' }}</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="text-xs text-zinc-500 uppercase">
                <tr>
                    <th class="px-5 py-2 font-medium">When</th>
                    <th class="px-5 py-2 font-medium">Who</th>
                    <th class="px-5 py-2 font-medium">Action</th>
                    <th class="px-5 py-2 font-medium">Subject</th>
                    <th class="px-5 py-2 font-medium">IP</th>
                    <th class="px-5 py-2 font-medium">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @foreach ($entries as $entry)
                    <tr class="align-top">
                        <td class="px-5 py-2 whitespace-nowrap text-zinc-500" title="{{ $entry->created_at }}">{{ $entry->created_at?->diffForHumans() }}</td>
                        <td class="px-5 py-2">
                            @if ($entry->panelAdmin)
                                {{ $entry->panelAdmin->email }}
                            @elseif ($entry->admin)
                                {{ $entry->admin->email }} <span class="text-xs text-zinc-500">(API)</span>
                            @else
                                <span class="text-zinc-500">System / CLI</span>
                            @endif
                        </td>
                        <td class="px-5 py-2 font-mono text-xs">{{ $entry->action }}</td>
                        <td class="px-5 py-2 text-xs">
                            @if ($entry->subject_type)
                                {{ $entry->subject_type }}
                                <span class="block font-mono text-zinc-500">{{ $entry->subject_id }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-5 py-2 font-mono text-xs text-zinc-500">{{ $entry->ip_address ?? '—' }}</td>
                        <td class="px-5 py-2 text-xs">
                            @if ($entry->before || $entry->after)
                                <details>
                                    <summary class="cursor-pointer text-zinc-500 hover:text-zinc-900">View</summary>
                                    @if ($entry->before)
                                        <p class="mt-2 font-medium">Before</p>
                                        <pre class="mt-1 max-w-md overflow-x-auto rounded bg-zinc-50 p-2">{{ json_encode($entry->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                    @endif
                                    @if ($entry->after)
                                        <p class="mt-2 font-medium">After</p>
                                        <pre class="mt-1 max-w-md overflow-x-auto rounded bg-zinc-50 p-2">{{ json_encode($entry->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
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
