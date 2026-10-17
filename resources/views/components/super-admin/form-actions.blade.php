@props(['cancel' => null, 'bleed' => true])

{{-- Sticks to the bottom of the viewport so the save button stays in reach on long forms. --}}
<div {{ $attributes->class([
    'sticky bottom-0 z-10 flex items-center justify-end gap-2 border-zinc-200/80 bg-zinc-100/85 py-3 backdrop-blur',
    '-mx-4 border-t px-4 sm:-mx-6 sm:px-6 lg:-mx-10 lg:px-10' => $bleed,
    'rounded-xl border px-3' => ! $bleed,
]) }}>
    @if ($cancel)
        <a href="{{ $cancel }}" class="sa-btn-secondary">Cancel</a>
    @endif
    {{ $slot }}
</div>
