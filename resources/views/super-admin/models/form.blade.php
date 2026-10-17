@php
    $editing = $entry->exists;
    $capability = fn (string $key) => old($key, data_get($entry->capabilities ?? [], $key));
@endphp

<x-super-admin.layout :title="$editing ? 'Edit '.$entry->display_name : 'Add a model'" :description="$editing ? $entry->slug : null" :back="$editing ? route('super-admin.models.show', $entry) : route('super-admin.models.index')" :back-label="$editing ? $entry->display_name : 'Model catalog'">
    <form method="POST" action="{{ $editing ? route('super-admin.models.update', $entry) : route('super-admin.models.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-super-admin.form-errors />

        <x-super-admin.card title="What people see">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                @unless ($editing)
                    <x-super-admin.field for="slug" label="Slug" hint="Can't be changed later.">
                        <input id="slug" name="slug" type="text" required maxlength="100" value="{{ old('slug') }}" placeholder="claude-sonnet-5" class="sa-input font-mono">
                    </x-super-admin.field>
                @endunless
                <x-super-admin.field for="display_name" label="Display name">
                    <input id="display_name" name="display_name" type="text" required maxlength="255" value="{{ old('display_name', $entry->display_name) }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="brand" label="Brand">
                    <input id="brand" name="brand" type="text" required maxlength="100" value="{{ old('brand', $entry->brand) }}" placeholder="anthropic" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="description" label="Description" class="sm:col-span-3">
                    <input id="description" name="description" type="text" maxlength="500" value="{{ old('description', $entry->description) }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="strengths" label="Strengths" hint="One per line, up to 6." class="sm:col-span-2">
                    <textarea id="strengths" name="strengths" rows="3" class="sa-input">{{ old('strengths', implode("\n", $entry->strengths ?? [])) }}</textarea>
                </x-super-admin.field>
                <div class="space-y-3">
                    <x-super-admin.field for="speed" label="Speed">
                        <select id="speed" name="speed" class="sa-input pr-8">
                            <option value="">—</option>
                            @foreach (\App\Enums\Ai\ModelSpeed::cases() as $speed)
                                <option value="{{ $speed->value }}" @selected(old('speed', $entry->speed?->value) === $speed->value)>{{ ucfirst($speed->value) }}</option>
                            @endforeach
                        </select>
                    </x-super-admin.field>
                    <x-super-admin.field for="cost_tier" label="Cost tier" hint="1 is the cheapest, 5 the most expensive.">
                        <input id="cost_tier" name="cost_tier" type="number" min="1" max="5" value="{{ old('cost_tier', $entry->cost_tier) }}" class="sa-input">
                    </x-super-admin.field>
                </div>
            </div>
        </x-super-admin.card>

        <x-super-admin.card title="Capabilities and availability">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                <x-super-admin.field for="context_window" label="Context window (tokens)">
                    <input id="context_window" name="context_window" type="number" min="1" value="{{ $capability('context_window') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="sort_order" label="Sort order">
                    <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $entry->sort_order ?? 0) }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="retires_at" label="Retires on">
                    <input id="retires_at" name="retires_at" type="date" value="{{ old('retires_at', $entry->retires_at?->format('Y-m-d')) }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="replacement_slug" label="Replaced by">
                    <select id="replacement_slug" name="replacement_slug" class="sa-input pr-8">
                        <option value="">—</option>
                        @foreach ($slugs as $slug)
                            <option value="{{ $slug }}" @selected(old('replacement_slug', $entry->replacement_slug) === $slug)>{{ $slug }}</option>
                        @endforeach
                    </select>
                </x-super-admin.field>
            </div>
            <div class="grid grid-cols-1 gap-1 border-t border-zinc-100 px-2 py-2 sm:grid-cols-2">
                <x-super-admin.switch name="vision" label="Understands images" description="Accepts image inputs." :checked="(bool) $capability('vision')" />
                <x-super-admin.switch name="tool_use" label="Can call tools" description="Agents can give it tools to use." :checked="(bool) $capability('tool_use')" />
                <x-super-admin.switch name="is_active" label="Available to pick" description="Turning this off stops every agent that uses it." :checked="(bool) old('is_active', $entry->is_active)" />
                <x-super-admin.switch name="is_internal" label="Internal only" description="Hidden from users; used by the platform itself." :checked="(bool) old('is_internal', $entry->is_internal)" />
            </div>
        </x-super-admin.card>

        <x-super-admin.form-actions :cancel="$editing ? route('super-admin.models.show', $entry) : route('super-admin.models.index')">
            <button type="submit" class="sa-btn-primary" @if ($editing && $entry->is_active) data-confirm="Save? Turning “Available to pick” off stops every agent that uses this model." @endif>{{ $editing ? 'Save model' : 'Add model' }}</button>
        </x-super-admin.form-actions>
    </form>
</x-super-admin.layout>
