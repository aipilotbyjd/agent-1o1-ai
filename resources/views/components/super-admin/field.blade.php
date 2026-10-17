@props(['for', 'label', 'hint' => null, 'error' => null, 'bag' => 'default'])

@php
    $errorKey = $error ?? $for;
    $message = $errors->getBag($bag)->first($errorKey);
@endphp

<div {{ $attributes->class(['sa-field', 'sa-field-invalid' => $message]) }}>
    <label for="{{ $for }}" class="sa-label">{{ $label }}</label>
    {{ $slot }}
    @if ($message)
        <p class="mt-1.5 flex items-start gap-1 text-xs text-red-600" id="{{ $for }}-error">
            <x-super-admin.icon name="warning" class="mt-px size-3.5" />
            {{ $message }}
        </p>
    @elseif ($hint)
        <p class="mt-1.5 text-xs text-zinc-500" id="{{ $for }}-hint">{{ $hint }}</p>
    @endif
</div>
