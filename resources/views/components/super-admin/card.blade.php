@props(['title' => null])

<section {{ $attributes->class(['rounded-xl border border-zinc-200 bg-white']) }}>
    @if ($title)
        <h2 class="border-b border-zinc-200 px-5 py-3 text-sm font-semibold">{{ $title }}</h2>
    @endif

    {{ $slot }}
</section>
