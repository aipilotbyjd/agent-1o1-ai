@props(['label' => 'Date', 'from' => null, 'to' => null, 'fromName' => 'from', 'toName' => 'to'])

@php $isActive = filled($from) || filled($to); @endphp

<div @class(['sa-filter', 'sa-filter-active' => $isActive])>
    <label for="filter-{{ $fromName }}" class="sa-filter-label">{{ $label }}</label>
    <input id="filter-{{ $fromName }}" name="{{ $fromName }}" type="date" value="{{ $from }}" class="sa-filter-control w-auto pr-1" aria-label="{{ $label }} from">
    <span class="px-1 text-zinc-400" aria-hidden="true">→</span>
    <input id="filter-{{ $toName }}" name="{{ $toName }}" type="date" value="{{ $to }}" class="sa-filter-control w-auto pr-2" aria-label="{{ $label }} to">
    @if ($isActive)
        <a href="{{ request()->fullUrlWithoutQuery([$fromName, $toName, 'page']) }}" class="sa-filter-clear" title="Remove {{ strtolower($label) }} filter">
            <x-super-admin.icon name="close" class="size-3.5" />
            <span class="sr-only">Remove {{ strtolower($label) }} filter</span>
        </a>
    @endif
</div>
