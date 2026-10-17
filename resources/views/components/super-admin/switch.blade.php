@props(['name', 'label', 'description' => null, 'checked' => false])

<label {{ $attributes->class(['flex cursor-pointer items-start justify-between gap-4 rounded-xl px-3 py-2.5 transition hover:bg-zinc-50']) }}>
    <span class="min-w-0">
        <span class="block text-sm font-medium text-zinc-800">{{ $label }}</span>
        @if ($description)
            <span class="mt-0.5 block text-xs text-zinc-500">{{ $description }}</span>
        @endif
    </span>
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" class="sa-switch mt-0.5" @checked($checked)>
</label>
