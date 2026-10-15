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
                    <div>
                        <label for="slug" class="sa-label">Slug (permanent)</label>
                        <input id="slug" name="slug" type="text" required maxlength="100" value="{{ old('slug') }}" placeholder="claude-sonnet-5" class="sa-input font-mono">
                    </div>
                @endunless
                <div>
                    <label for="display_name" class="sa-label">Display name</label>
                    <input id="display_name" name="display_name" type="text" required maxlength="255" value="{{ old('display_name', $entry->display_name) }}" class="sa-input">
                </div>
                <div>
                    <label for="brand" class="sa-label">Brand</label>
                    <input id="brand" name="brand" type="text" required maxlength="100" value="{{ old('brand', $entry->brand) }}" placeholder="anthropic" class="sa-input">
                </div>
                <div class="sm:col-span-3">
                    <label for="description" class="sa-label">Description</label>
                    <input id="description" name="description" type="text" maxlength="500" value="{{ old('description', $entry->description) }}" class="sa-input">
                </div>
                <div class="sm:col-span-2">
                    <label for="strengths" class="sa-label">Strengths (one per line, up to 6)</label>
                    <textarea id="strengths" name="strengths" rows="3" class="sa-input">{{ old('strengths', implode("\n", $entry->strengths ?? [])) }}</textarea>
                </div>
                <div class="space-y-3">
                    <div>
                        <label for="speed" class="sa-label">Speed</label>
                        <select id="speed" name="speed" class="sa-input pr-8">
                            <option value="">—</option>
                            @foreach (\App\Enums\Ai\ModelSpeed::cases() as $speed)
                                <option value="{{ $speed->value }}" @selected(old('speed', $entry->speed?->value) === $speed->value)>{{ $speed->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="cost_tier" class="sa-label">Cost tier (1 = cheapest)</label>
                        <input id="cost_tier" name="cost_tier" type="number" min="1" max="5" value="{{ old('cost_tier', $entry->cost_tier) }}" class="sa-input">
                    </div>
                </div>
            </div>
        </x-super-admin.card>

        <x-super-admin.card title="Capabilities and availability">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                <div>
                    <label for="context_window" class="sa-label">Context window (tokens)</label>
                    <input id="context_window" name="context_window" type="number" min="1" value="{{ $capability('context_window') }}" class="sa-input">
                </div>
                <div>
                    <label for="sort_order" class="sa-label">Sort order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $entry->sort_order ?? 0) }}" class="sa-input">
                </div>
                <div>
                    <label for="retires_at" class="sa-label">Retires on</label>
                    <input id="retires_at" name="retires_at" type="date" value="{{ old('retires_at', $entry->retires_at?->format('Y-m-d')) }}" class="sa-input">
                </div>
                <div>
                    <label for="replacement_slug" class="sa-label">Replaced by</label>
                    <select id="replacement_slug" name="replacement_slug" class="sa-input pr-8">
                        <option value="">—</option>
                        @foreach ($slugs as $slug)
                            <option value="{{ $slug }}" @selected(old('replacement_slug', $entry->replacement_slug) === $slug)>{{ $slug }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap items-end gap-5 pb-2 sm:col-span-2">
                    @foreach (['vision' => ['Understands images', (bool) $capability('vision')], 'tool_use' => ['Can call tools', (bool) $capability('tool_use')], 'is_active' => ['Available to pick', (bool) old('is_active', $entry->is_active)], 'is_internal' => ['Internal only (hidden from users)', (bool) old('is_internal', $entry->is_internal)]] as $field => [$label, $isChecked])
                        <label class="flex items-center gap-2 text-sm text-zinc-700">
                            <input type="hidden" name="{{ $field }}" value="0">
                            <input type="checkbox" name="{{ $field }}" value="1" class="size-4 rounded border-zinc-300 accent-zinc-900" @checked($isChecked)>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
        </x-super-admin.card>

        <div class="flex justify-end gap-2">
            <a href="{{ $editing ? route('super-admin.models.show', $entry) : route('super-admin.models.index') }}" class="sa-btn-secondary">Cancel</a>
            <button type="submit" class="sa-btn-primary" @if ($editing && $entry->is_active) data-confirm="Save? Turning “Available to pick” off stops every agent that uses this model." @endif>{{ $editing ? 'Save model' : 'Add model' }}</button>
        </div>
    </form>
</x-super-admin.layout>
