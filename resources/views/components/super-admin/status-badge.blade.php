@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;

    $tone = match ($value) {
        'completed', 'active', 'trialing', 'enabled', 'yes' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'failed', 'past_due', 'unpaid', 'incomplete_expired', 'disabled', 'deleted' => 'bg-red-50 text-red-700 ring-red-200',
        'running', 'pending', 'awaiting_approval', 'awaiting_callback', 'incomplete' => 'bg-amber-50 text-amber-800 ring-amber-200',
        default => 'bg-zinc-100 text-zinc-700 ring-zinc-200',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', $tone]) }}>{{ str_replace('_', ' ', $value) }}</span>
