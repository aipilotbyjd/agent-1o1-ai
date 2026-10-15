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
                <div>
                    <label for="name" class="sa-label">Name</label>
                    <input id="name" name="name" type="text" required maxlength="255" value="{{ old('name', $collection->name) }}" class="sa-input">
                </div>
                <div>
                    <label for="slug" class="sa-label">Slug {{ $editing ? '' : '(blank to generate)' }}</label>
                    <input id="slug" name="slug" type="text" maxlength="255" value="{{ old('slug', $collection->slug) }}" class="sa-input font-mono">
                </div>
                <div class="sm:col-span-2">
                    <label for="description" class="sa-label">Description</label>
                    <textarea id="description" name="description" rows="3" maxlength="2000" class="sa-input">{{ old('description', $collection->description) }}</textarea>
                </div>
                <div>
                    <label for="category" class="sa-label">Category</label>
                    <input id="category" name="category" type="text" maxlength="100" value="{{ old('category', $collection->category) }}" class="sa-input">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="icon" class="sa-label">Icon</label>
                        <input id="icon" name="icon" type="text" maxlength="100" value="{{ old('icon', $collection->icon) }}" class="sa-input">
                    </div>
                    <div>
                        <label for="color" class="sa-label">Colour</label>
                        <input id="color" name="color" type="text" maxlength="32" value="{{ old('color', $collection->color) }}" class="sa-input font-mono">
                    </div>
                </div>
            </div>
        </x-super-admin.card>

        <div class="flex justify-end">
            <button type="submit" class="sa-btn-primary">{{ $editing ? 'Save collection' : 'Create collection' }}</button>
        </div>
    </form>
</x-super-admin.layout>
