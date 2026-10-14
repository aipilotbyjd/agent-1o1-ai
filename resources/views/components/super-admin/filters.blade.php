@props(['action', 'active' => false])

<form method="GET" action="{{ $action }}" {{ $attributes->class(['flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-xs ring-1 ring-zinc-200/80']) }}>
    {{ $slot }}

    <div class="flex items-center gap-2">
        <button type="submit" class="sa-btn-primary">
            <x-super-admin.icon name="filter" class="size-4" />
            Filter
        </button>
        @if ($active)
            <a href="{{ $action }}" class="sa-btn-secondary">Clear</a>
        @endif
    </div>
</form>
