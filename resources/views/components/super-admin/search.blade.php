@props(['name' => 'q', 'value' => '', 'label' => 'Search', 'placeholder' => ''])

<div {{ $attributes->class(['relative w-full sm:w-auto sm:min-w-72 sm:flex-1 sm:basis-72']) }}>
    <label for="filter-{{ $name }}" class="sr-only">{{ $label }}</label>
    <x-super-admin.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />
    <input id="filter-{{ $name }}" name="{{ $name }}" type="search" value="{{ $value }}" placeholder="{{ $placeholder ?: $label }}" class="sa-input mt-0 h-9 py-0 pr-10 pl-9" data-search-shortcut>
    <kbd class="pointer-events-none absolute top-1/2 right-2.5 hidden -translate-y-1/2 rounded border border-zinc-200 bg-zinc-50 px-1.5 font-sans text-[11px] text-zinc-400 sm:block">/</kbd>
</div>
