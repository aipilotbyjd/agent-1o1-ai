@props(['title' => null, 'description' => null])

<section {{ $attributes->class(['overflow-hidden rounded-2xl bg-white shadow-xs ring-1 ring-zinc-200/80']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4">
            <div>
                @if ($title)
                    <h2 class="text-sm font-semibold text-zinc-900">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-xs text-zinc-500">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2 text-xs">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    {{ $slot }}

    @isset($footer)
        <footer class="border-t border-zinc-100 bg-zinc-50/50 px-5 py-2.5 text-right text-xs">{{ $footer }}</footer>
    @endisset
</section>
