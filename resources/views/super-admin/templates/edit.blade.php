<x-super-admin.layout :title="'Edit '.$template->name" :description="ucfirst($kind).' template · '.($template->isPublic() ? 'published' : 'hidden draft')" :back="route('super-admin.templates.index', ['kind' => $kind])" back-label="Templates">
    <x-slot:actions>
        <form method="POST" action="{{ route('super-admin.templates.publish', [$kind, $template->id]) }}" @if (! $template->isPublic()) data-confirm="Publish “{{ $template->name }}” to every workspace?" @endif>
            @csrf
            <input type="hidden" name="published" value="{{ $template->isPublic() ? 0 : 1 }}">
            <button type="submit" class="{{ $template->isPublic() ? 'sa-btn-secondary' : 'sa-btn-primary' }}">{{ $template->isPublic() ? 'Hide from gallery' : 'Publish to gallery' }}</button>
        </form>
    </x-slot:actions>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('super-admin.templates.update', [$kind, $template->id]) }}" class="space-y-6 lg:col-span-2">
            @csrf
            @method('PUT')

            <x-super-admin.form-errors />

            <x-super-admin.card title="Gallery listing">
                <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-2">
                    <x-super-admin.field for="name" label="Name">
                        <input id="name" name="name" type="text" required maxlength="255" value="{{ old('name', $template->name) }}" class="sa-input">
                    </x-super-admin.field>
                    <x-super-admin.field for="slug" label="Slug">
                        <input id="slug" name="slug" type="text" maxlength="255" value="{{ old('slug', $template->slug) }}" class="sa-input font-mono">
                    </x-super-admin.field>
                    <x-super-admin.field for="description" label="Description" class="sm:col-span-2">
                        <textarea id="description" name="description" rows="3" maxlength="2000" class="sa-input">{{ old('description', $template->description) }}</textarea>
                    </x-super-admin.field>
                    <x-super-admin.field for="category" label="Category">
                        <input id="category" name="category" type="text" maxlength="100" value="{{ old('category', $template->category) }}" class="sa-input">
                    </x-super-admin.field>
                    <div class="grid grid-cols-2 gap-3">
                        <x-super-admin.field for="icon" label="Icon">
                            <input id="icon" name="icon" type="text" maxlength="100" value="{{ old('icon', $template->icon) }}" class="sa-input">
                        </x-super-admin.field>
                        <x-super-admin.field for="color" label="Colour">
                            <input id="color" name="color" type="text" maxlength="32" value="{{ old('color', $template->color) }}" class="sa-input font-mono">
                        </x-super-admin.field>
                    </div>
                </div>
            </x-super-admin.card>

            <x-super-admin.form-actions :cancel="route('super-admin.templates.index', ['kind' => $kind])" :bleed="false">
                <button type="submit" class="sa-btn-primary">Save listing</button>
            </x-super-admin.form-actions>
        </form>

        <div class="space-y-6">
            <x-super-admin.card title="Contents" description="Built in the product; review before publishing.">
                @php $contents = $kind === 'workflow' ? ($template->graph ?? []) : ($template->config ?? []); @endphp
                <dl class="sa-dl">
                    @if ($kind === 'workflow')
                        <div><dt>Steps</dt><dd class="tabular-nums">{{ count($contents['nodes'] ?? []) }}</dd></div>
                        <div><dt>Connections</dt><dd class="tabular-nums">{{ count($contents['edges'] ?? []) }}</dd></div>
                        <div>
                            <dt>Step types</dt>
                            <dd class="text-xs">{{ collect($contents['nodes'] ?? [])->pluck('type')->filter()->unique()->implode(', ') ?: '—' }}</dd>
                        </div>
                    @else
                        <div><dt>Model</dt><dd class="font-mono text-xs">{{ $contents['model'] ?? '—' }}</dd></div>
                        <div><dt>Tools</dt><dd class="tabular-nums">{{ count($contents['tool_bindings'] ?? []) }}</dd></div>
                        <div><dt>Instructions</dt><dd class="text-xs">{{ \Illuminate\Support\Str::limit($contents['instructions'] ?? '', 160) ?: '—' }}</dd></div>
                    @endif
                    <div><dt>Uses</dt><dd class="tabular-nums">{{ number_format($template->usage_count) }}</dd></div>
                </dl>
            </x-super-admin.card>

            <x-super-admin.card title="Remove from gallery" description="Also takes it out of every collection.">
                <form method="POST" action="{{ route('super-admin.templates.destroy', [$kind, $template->id]) }}" class="px-5 py-4" data-confirm="Remove “{{ $template->name }}” from the gallery?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="sa-btn-secondary w-full text-red-700">Remove template</button>
                </form>
            </x-super-admin.card>
        </div>
    </div>
</x-super-admin.layout>
