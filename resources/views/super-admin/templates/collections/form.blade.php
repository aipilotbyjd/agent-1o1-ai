@php $editing = $collection->exists; @endphp

<x-super-admin.layout :title="$editing ? 'Edit '.$collection->name : 'New collection'" :back="$editing ? route('super-admin.templates.collections.show', $collection) : route('super-admin.templates.collections.index')" :back-label="$editing ? $collection->name : 'Collections'">
    <form method="POST" action="{{ $editing ? route('super-admin.templates.collections.update', $collection) : route('super-admin.templates.collections.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-super-admin.form-errors />

        <x-super-admin.card title="Collection">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-2">
                <x-super-admin.field for="name" label="Name">
                    <input id="name" name="name" type="text" required maxlength="255" value="{{ old('name', $collection->name) }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="slug" label="Slug" :hint="$editing ? null : 'Leave blank to generate it from the name.'">
                    <input id="slug" name="slug" type="text" maxlength="255" value="{{ old('slug', $collection->slug) }}" class="sa-input font-mono">
                </x-super-admin.field>
                <x-super-admin.field for="description" label="Description" class="sm:col-span-2">
                    <textarea id="description" name="description" rows="3" maxlength="2000" class="sa-input">{{ old('description', $collection->description) }}</textarea>
                </x-super-admin.field>
                <x-super-admin.field for="category" label="Category">
                    <input id="category" name="category" type="text" maxlength="100" value="{{ old('category', $collection->category) }}" class="sa-input">
                </x-super-admin.field>
                <div class="grid grid-cols-2 gap-3">
                    <x-super-admin.field for="icon" label="Icon">
                        <input id="icon" name="icon" type="text" maxlength="100" value="{{ old('icon', $collection->icon) }}" class="sa-input">
                    </x-super-admin.field>
                    <x-super-admin.field for="color" label="Colour">
                        <input id="color" name="color" type="text" maxlength="32" value="{{ old('color', $collection->color) }}" class="sa-input font-mono">
                    </x-super-admin.field>
                </div>
            </div>
        </x-super-admin.card>

        <x-super-admin.form-actions :cancel="$editing ? route('super-admin.templates.collections.show', $collection) : route('super-admin.templates.collections.index')">
            <button type="submit" class="sa-btn-primary">{{ $editing ? 'Save collection' : 'Create collection' }}</button>
        </x-super-admin.form-actions>
    </form>
</x-super-admin.layout>
