<x-admin.layout title="Workspaces">
    <form method="GET" action="{{ route('panel.workspaces.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-64 flex-1">
            <label for="q" class="block text-xs font-medium text-zinc-500">Search</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Name, slug, ID or owner email"
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none">
        </div>
        <div>
            <label for="deleted" class="block text-xs font-medium text-zinc-500">Deleted</label>
            <select id="deleted" name="deleted" class="mt-1 block rounded-md border border-zinc-300 px-3 py-2 text-sm">
                <option value="">Hide</option>
                <option value="with" @selected(($filters['deleted'] ?? '') === 'with')>Include</option>
                <option value="only" @selected(($filters['deleted'] ?? '') === 'only')>Only deleted</option>
            </select>
        </div>
        <button type="submit" class="rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-700">Filter</button>
        @if (array_filter($filters))
            <a href="{{ route('panel.workspaces.index') }}" class="py-2 text-sm text-zinc-500 hover:text-zinc-900">Clear</a>
        @endif
    </form>

    <x-admin.card class="mt-6">
        @if ($workspaces->isEmpty())
            <p class="px-5 py-6 text-sm text-zinc-500">No workspaces match.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-zinc-500 uppercase">
                        <tr>
                            <th class="px-5 py-2 font-medium">Workspace</th>
                            <th class="px-5 py-2 font-medium">Owner</th>
                            <th class="px-5 py-2 font-medium">Members</th>
                            <th class="px-5 py-2 font-medium">Workflows</th>
                            <th class="px-5 py-2 font-medium">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($workspaces as $workspace)
                            <tr class="hover:bg-zinc-50">
                                <td class="px-5 py-2">
                                    <a href="{{ route('panel.workspaces.show', $workspace) }}" class="font-medium hover:underline">{{ $workspace->name }}</a>
                                    @if ($workspace->trashed())
                                        <x-admin.status-badge status="deleted" class="ml-1" />
                                    @endif
                                    <p class="font-mono text-xs text-zinc-500">{{ $workspace->slug }}</p>
                                </td>
                                <td class="px-5 py-2">
                                    @if ($workspace->owner)
                                        <a href="{{ route('panel.users.show', $workspace->owner) }}" class="hover:underline">{{ $workspace->owner->email }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-2 tabular-nums">{{ $workspace->members_count }}</td>
                                <td class="px-5 py-2 tabular-nums">{{ $workspace->workflows_count }}</td>
                                <td class="px-5 py-2 whitespace-nowrap text-zinc-500">{{ $workspace->created_at?->toFormattedDateString() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.card>

    <div class="mt-4">{{ $workspaces->links() }}</div>
</x-admin.layout>
