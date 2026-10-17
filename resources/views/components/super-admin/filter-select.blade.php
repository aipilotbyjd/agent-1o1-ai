@props(['name', 'label', 'options', 'value' => null, 'placeholder' => 'Any'])

@php
    $options = is_string($options) && enum_exists($options)
        ? collect($options::cases())->mapWithKeys(fn (\BackedEnum $case): array => [$case->value => ucfirst(str_replace('_', ' ', $case->value))])
        : collect($options);
    $value = (string) ($value ?? '');
    $isActive = $value !== '';
@endphp

<div @class(['sa-filter', 'sa-filter-active' => $isActive])>
    <label for="filter-{{ $name }}" class="sa-filter-label">{{ $label }}</label>
    <select id="filter-{{ $name }}" name="{{ $name }}" @class(['sa-filter-control max-w-56 truncate', 'pr-8' => ! $isActive])>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($value === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($isActive)
        <a href="{{ request()->fullUrlWithoutQuery([$name, 'page']) }}" class="sa-filter-clear" title="Remove {{ strtolower($label) }} filter">
            <x-super-admin.icon name="close" class="size-3.5" />
            <span class="sr-only">Remove {{ strtolower($label) }} filter</span>
        </a>
    @else
        <x-super-admin.icon name="chevron-down" class="pointer-events-none absolute right-2.5 size-3.5 text-zinc-400" />
    @endif
</div>
