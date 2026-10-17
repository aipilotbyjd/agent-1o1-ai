@props(['label'])

<div {{ $attributes->class(['rounded-2xl bg-white p-5 shadow-xs ring-1 ring-zinc-200/80']) }}>
    <p class="text-sm font-medium text-zinc-500">{{ $label }}</p>
    <div class="mt-2 text-xl font-semibold tracking-tight tabular-nums">{{ $slot }}</div>
    @isset($footer)
        {{ $footer }}
    @endisset
</div>
