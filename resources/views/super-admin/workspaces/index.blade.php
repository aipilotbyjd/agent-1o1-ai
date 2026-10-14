<x-super-admin.layout title="Workspaces" :description="number_format($workspaces->total()).' '.str('workspace')->plural($workspaces->total())">
    <x-super-admin.filters :action="route('super-admin.workspaces.index')" :active="(bool) array_filter($filters)">
        <x-super-admin.search :value="$filters['q'] ?? ''" placeholder="Name, slug, ID or owner email" />
        <div>
            <label for="deleted" class="sa-label">Deleted</label>
            <select id="deleted" name="deleted" class="sa-input pr-8">
                <option value="">Hide</option>
                <option value="with" @selected(($filters['deleted'] ?? '') === 'with')>Include</option>
                <option value="only" @selected(($filters['deleted'] ?? '') === 'only')>Only deleted</option>
            </select>
        </div>
    </x-super-admin.filters>

    <x-super-admin.card class="mt-6">
        @if ($workspaces->isEmpty())
            <x-super-admin.empty icon="workspace">No workspaces match.</x-super-admin.empty>
        @else
            <div class="overflow-x-auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Workspace</th>
                            <th>Owner</th>
                            <th class="text-right">Members</th>
                            <th class="text-right">Workflows</th>
                            <th class="text-right">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($workspaces as $workspace)
                            <tr class="relative transition hover:bg-zinc-50/80">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500">
                                            <x-super-admin.icon name="workspace" class="size-4" />
                                        </span>
                                        <div class="min-w-0">
                                            <a href="{{ route('super-admin.workspaces.show', $workspace) }}" class="font-medium text-zinc-900 after:absolute after:inset-0">{{ $workspace->name }}</a>
                                            @if ($workspace->trashed())
                                                <x-super-admin.status-badge status="deleted" class="ml-1" />
                                            @endif
                                            <p class="font-mono text-xs text-zinc-400">{{ $workspace->slug }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-zinc-600">
                                    @if ($workspace->owner)
                                        <a href="{{ route('super-admin.users.show', $workspace->owner) }}" class="relative z-10 hover:text-zinc-900 hover:underline">{{ $workspace->owner->email }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right text-zinc-700 tabular-nums">{{ $workspace->members_count }}</td>
                                <td class="text-right text-zinc-700 tabular-nums">{{ $workspace->workflows_count }}</td>
                                <td class="text-right whitespace-nowrap text-zinc-500">{{ $workspace->created_at?->toFormattedDateString() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-super-admin.card>

    <div class="mt-4">{{ $workspaces->links() }}</div>
</x-super-admin.layout>
