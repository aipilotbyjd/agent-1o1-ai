@props(['name', 'checked' => false])

<label class="sa-filter cursor-pointer gap-2 px-3 font-medium text-zinc-700 select-none has-checked:text-indigo-900">
    <input type="checkbox" name="{{ $name }}" value="1" class="size-4 rounded border-zinc-300 accent-indigo-600" @checked($checked)>
    {{ $slot }}
</label>
