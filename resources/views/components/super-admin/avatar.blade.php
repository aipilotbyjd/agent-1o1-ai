@props(['name', 'size' => 'size-8'])

@php
    $label = trim((string) $name);
    $words = preg_split('/[\s@._-]+/', $label, -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $initials = mb_strtoupper(mb_substr($words[0], 0, 1).(isset($words[1]) ? mb_substr($words[1], 0, 1) : ''));
    $palette = ['bg-indigo-100 text-indigo-700', 'bg-sky-100 text-sky-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-800', 'bg-rose-100 text-rose-700', 'bg-violet-100 text-violet-700'];
    $tone = $palette[crc32($label) % count($palette)];
@endphp

<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center rounded-full text-xs font-semibold', $size, $tone]) }} aria-hidden="true">{{ $initials }}</span>
