@props(['action', 'active' => false])

<form method="GET" action="{{ $action }}" data-auto-submit {{ $attributes->class(['flex flex-wrap items-center gap-2']) }} role="search">
    {{ $slot }}

    <div class="ml-auto flex items-center gap-1">
        @if ($active)
            <a href="{{ $action }}" class="inline-flex h-9 items-center gap-1 rounded-lg px-2.5 text-sm font-medium text-zinc-500 transition hover:bg-white hover:text-zinc-900 hover:shadow-xs">
                <x-super-admin.icon name="close" class="size-4" />
                Clear all
            </a>
        @endif
        <button type="submit" class="sa-btn-primary h-9 py-0">Apply</button>
    </div>
</form>
