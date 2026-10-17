@props(['name', 'label', 'value' => null, 'placeholder' => '', 'mono' => false])

@php $isActive = filled($value); @endphp

<div {{ $attributes->class(['sa-filter', 'sa-filter-active' => $isActive]) }}>
    <label for="filter-{{ $name }}" class="sa-filter-label">{{ $label }}</label>
    <input id="filter-{{ $name }}" name="{{ $name }}" type="text" value="{{ $value }}" placeholder="{{ $placeholder }}" @class(['sa-filter-control w-full', 'font-mono text-xs' => $mono])>
    @if ($isActive)
        <a href="{{ request()->fullUrlWithoutQuery([$name, 'page']) }}" class="sa-filter-clear" title="Remove {{ strtolower($label) }} filter">
            <x-super-admin.icon name="close" class="size-3.5" />
            <span class="sr-only">Remove {{ strtolower($label) }} filter</span>
        </a>
    @endif
</div>
