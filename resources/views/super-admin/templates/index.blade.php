<x-super-admin.layout title="Templates" description="The gallery every workspace browses, and the templates workspaces keep for themselves">
    <div class="flex flex-wrap items-center gap-2">
        @foreach (['workflow' => 'Workflow templates', 'agent' => 'Agent templates'] as $kindKey => $label)
            <a href="{{ route('super-admin.templates.index', ['kind' => $kindKey, 'scope' => $scope]) }}" @class(['sa-btn-secondary', 'ring-indigo-500 text-indigo-700' => $kind === $kindKey])>{{ $label }}</a>
        @endforeach
        <span class="mx-2 h-5 w-px bg-zinc-200"></span>
        @foreach (['gallery' => 'Gallery', 'workspaces' => 'Workspace templates'] as $scopeKey => $label)
            <a href="{{ route('super-admin.templates.index', ['kind' => $kind, 'scope' => $scopeKey]) }}" @class(['sa-btn-secondary', 'ring-indigo-500 text-indigo-700' => $scope === $scopeKey])>{{ $label }}</a>
        @endforeach
    </div>

    <x-super-admin.filters :action="route('super-admin.templates.index')" :active="filled($filters['q'] ?? null)" class="mt-4">
        <input type="hidden" name="kind" value="{{ $kind }}">
        <input type="hidden" name="scope" value="{{ $scope }}">
        <x-super-admin.search :value="$filters['q'] ?? ''" placeholder="Template name" />
    </x-super-admin.filters>

    <x-super-admin.card class="mt-6" :description="$scope === 'workspaces' ? 'Read-only. Copy one into the gallery to share it with every workspace.' : null" :title="$scope === 'workspaces' ? 'Workspace templates' : null">
        @if ($templates->isEmpty())
            <x-super-admin.empty icon="inbox">No templates here.</x-super-admin.empty>
        @else
            <div class="overflow-x-auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Template</th>
                            <th>Category</th>
                            @if ($scope === 'workspaces')
                                <th>Workspace</th>
                            @else
                                <th>Gallery</th>
                            @endif
                            <th class="text-right">Uses</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($templates as $template)
                            <tr class="hover:bg-zinc-50/70">
                                <td>
                                    <p class="font-medium text-zinc-900">{{ $template->name }}</p>
                                    <p class="max-w-md truncate text-xs text-zinc-500">{{ $template->description }}</p>
                                </td>
                                <td class="text-zinc-600">{{ $template->category ?? '—' }}</td>
                                @if ($scope === 'workspaces')
                                    <td>
                                        @if ($template->workspace)
                                            <a href="{{ route('super-admin.workspaces.show', $template->workspace) }}" class="sa-link">{{ $template->workspace->name }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                @else
                                    <td><x-super-admin.status-badge :status="$template->isPublic() ? 'active' : 'hidden'" /></td>
                                @endif
                                <td class="text-right tabular-nums">{{ number_format($template->usage_count) }}</td>
                                <td>
                                    <div class="flex justify-end gap-1.5">
                                        @if ($scope === 'workspaces')
                                            <form method="POST" action="{{ route('super-admin.templates.copy-to-gallery', [$kind, $template->id]) }}" data-confirm="Copy “{{ $template->name }}” into the gallery as a hidden draft?">
                                                @csrf
                                                <button type="submit" class="sa-btn-secondary">Copy to gallery</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('super-admin.templates.publish', [$kind, $template->id]) }}" @if (! $template->isPublic()) data-confirm="Publish “{{ $template->name }}” to every workspace?" @endif>
                                                @csrf
                                                <input type="hidden" name="published" value="{{ $template->isPublic() ? 0 : 1 }}">
                                                <button type="submit" class="sa-btn-secondary">{{ $template->isPublic() ? 'Hide' : 'Publish' }}</button>
                                            </form>
                                            <a href="{{ route('super-admin.templates.edit', [$kind, $template->id]) }}" class="sa-btn-secondary">Edit</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-super-admin.card>

    <div class="mt-4">{{ $templates->links() }}</div>
</x-super-admin.layout>
