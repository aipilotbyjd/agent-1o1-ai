@props(['icon' => 'inbox', 'clear' => null])

<div {{ $attributes->class(['flex flex-col items-center justify-center gap-2 px-5 py-12 text-center']) }}>
    <span class="flex size-10 items-center justify-center rounded-full bg-zinc-100 text-zinc-400">
        <x-super-admin.icon :name="$icon" class="size-5" />
    </span>
    <p class="text-sm text-zinc-500">{{ $slot }}</p>
    @if ($clear)
        <a href="{{ $clear }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Clear filters</a>
    @endif
</div>
