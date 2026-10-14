@props(['icon' => 'inbox'])

<div {{ $attributes->class(['flex flex-col items-center justify-center gap-2 px-5 py-10 text-center']) }}>
    <span class="flex size-10 items-center justify-center rounded-full bg-zinc-100 text-zinc-400">
        <x-super-admin.icon :name="$icon" class="size-5" />
    </span>
    <p class="text-sm text-zinc-500">{{ $slot }}</p>
</div>
