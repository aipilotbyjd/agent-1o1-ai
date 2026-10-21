@props(['name' => 'q', 'value' => '', 'label' => 'Search', 'placeholder' => ''])

<div {{ $attributes->class(['min-w-64 flex-1']) }}>
    <label for="{{ $name }}" class="sa-label">{{ $label }}</label>
    <div class="relative">
        <x-super-admin.icon name="search" class="pointer-events-none absolute top-1/2 left-3 mt-0.75 size-4 -translate-y-1/2 text-zinc-400" />
        <input id="{{ $name }}" name="{{ $name }}" type="search" value="{{ $value }}" placeholder="{{ $placeholder }}" class="sa-input pl-9">
    </div>
</div>
