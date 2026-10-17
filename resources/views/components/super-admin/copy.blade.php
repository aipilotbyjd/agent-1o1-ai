@props(['value', 'label' => 'Copy'])

<button type="button" data-copy="{{ $value }}" {{ $attributes->class(['relative z-10 inline-flex shrink-0 items-center justify-center rounded-md p-1 align-middle text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 focus-visible:outline-2 focus-visible:outline-indigo-500']) }} title="{{ $label }}">
    <x-super-admin.icon name="copy" class="size-3.5" data-copy-idle />
    <x-super-admin.icon name="check" class="hidden size-3.5 text-emerald-600" data-copy-done />
    <span class="sr-only">{{ $label }}</span>
</button>
