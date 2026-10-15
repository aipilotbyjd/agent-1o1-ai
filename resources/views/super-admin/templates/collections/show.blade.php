<x-super-admin.layout :title="$collection->name" :description="$collection->description ?: ($collection->isPublic() ? 'Published' : 'Hidden draft')" :back="route('super-admin.templates.collections.index')" back-label="Collections">
    <x-slot:actions>
        <form method="POST" action="{{ route('super-admin.templates.collections.publish', $collection) }}" @if (! $collection->isPublic()) data-confirm="Publish “{{ $collection->name }}” to every workspace?" @endif>
            @csrf
            <input type="hidden" name="published" value="{{ $collection->isPublic() ? 0 : 1 }}">
            <button type="submit" class="{{ $collection->isPublic() ? 'sa-btn-secondary' : 'sa-btn-primary' }}">{{ $collection->isPublic() ? 'Hide' : 'Publish' }}</button>
        </form>
        <a href="{{ route('super-admin.templates.collections.edit', $collection) }}" class="sa-btn-secondary">Edit details</a>
    </x-slot:actions>

    <x-super-admin.form-errors class="mb-6" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-super-admin.card title="Templates in this collection" class="lg:col-span-2">
            @if ($collection->items->isEmpty())
                <x-super-admin.empty icon="inbox">Empty. Add gallery templates on the right.</x-super-admin.empty>
            @else
                <ul class="divide-y divide-zinc-100 text-sm">
                    @foreach ($collection->items as $item)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="font-medium">{{ $item->templatable?->name ?? 'Removed template' }}</p>
                                <p class="text-xs text-zinc-500">{{ str_replace('_', ' ', $item->templatable_type) }}
                                    @if ($item->templatable && ! $item->templatable->isPublic())
                                        · <span class="text-amber-700">hidden in the gallery</span>
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-1.5">
                                @foreach (['up' => 'arrow-up', 'down' => 'arrow-down'] as $direction => $icon)
                                    <form method="POST" action="{{ route('super-admin.templates.collections.items.move', [$collection, $item]) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="{{ $direction }}">
                                        <button type="submit" class="rounded-md p-1.5 text-zinc-500 ring-1 ring-zinc-200 hover:bg-zinc-50 disabled:opacity-30" @disabled(($direction === 'up' && $loop->parent->first) || ($direction === 'down' && $loop->parent->last)) title="Move {{ $direction }}">
                                            <x-super-admin.icon :name="$icon" class="size-4" />
                                        </button>
                                    </form>
                                @endforeach
                                <form method="POST" action="{{ route('super-admin.templates.collections.items.destroy', [$collection, $item]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="sa-btn-secondary">Remove</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-super-admin.card>

        <div class="space-y-6">
            <x-super-admin.card title="Add a template" description="Only gallery templates can be added.">
                <form method="POST" action="{{ route('super-admin.templates.collections.items.store', $collection) }}" class="space-y-3 px-5 py-4">
                    @csrf
                    <select name="template" required class="sa-input pr-8">
                        @foreach (['workflow' => 'Workflow templates', 'agent' => 'Agent templates'] as $kind => $label)
                            <optgroup label="{{ $label }}">
                                @foreach ($available[$kind] as $template)
                                    <option value="{{ $kind }}:{{ $template->id }}">{{ $template->name }}{{ $template->visibility === 'public' ? '' : ' (hidden)' }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <button type="submit" class="sa-btn-primary w-full">Add</button>
                </form>
            </x-super-admin.card>

            <x-super-admin.card title="Delete collection" description="The templates themselves stay in the gallery.">
                <form method="POST" action="{{ route('super-admin.templates.collections.destroy', $collection) }}" class="px-5 py-4" data-confirm="Delete the collection {{ $collection->name }}?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="sa-btn-secondary w-full text-red-700">Delete collection</button>
                </form>
            </x-super-admin.card>
        </div>
    </div>
</x-super-admin.layout>
