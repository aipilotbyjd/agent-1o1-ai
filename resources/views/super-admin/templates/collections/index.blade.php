<x-super-admin.layout title="Template collections" description="Packs of templates in the gallery">
    <x-slot:actions>
        <a href="{{ route('super-admin.templates.collections.create') }}" class="sa-btn-primary">New collection</a>
    </x-slot:actions>

    <x-super-admin.card>
        @if ($collections->isEmpty())
            <x-super-admin.empty icon="folder">No gallery collections yet.</x-super-admin.empty>
        @else
            <div class="overflow-x-auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Collection</th>
                            <th>Category</th>
                            <th>Gallery</th>
                            <th class="text-right">Templates</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($collections as $collection)
                            <tr>
                                <td>
                                    <a href="{{ route('super-admin.templates.collections.show', $collection) }}" class="sa-row-link">{{ $collection->name }}</a>
                                    <p class="max-w-md truncate text-xs text-zinc-500">{{ $collection->description }}</p>
                                </td>
                                <td class="text-zinc-600">{{ $collection->category ?? '—' }}</td>
                                <td><x-super-admin.status-badge :status="$collection->isPublic() ? 'active' : 'hidden'" /></td>
                                <td class="text-right tabular-nums">{{ $collection->items_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-super-admin.card>
</x-super-admin.layout>
