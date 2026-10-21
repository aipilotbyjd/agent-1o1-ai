@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;

    [$tone, $dot] = match ($value) {
        'completed', 'active', 'trialing', 'enabled', 'verified', 'yes' => ['bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'bg-emerald-500'],
        'failed', 'past_due', 'unpaid', 'incomplete_expired', 'disabled', 'deleted' => ['bg-red-50 text-red-700 ring-red-600/20', 'bg-red-500'],
        'running', 'pending', 'awaiting_approval', 'awaiting_callback', 'incomplete', 'unverified' => ['bg-amber-50 text-amber-800 ring-amber-600/25', 'bg-amber-500'],
        default => ['bg-zinc-50 text-zinc-600 ring-zinc-500/20', 'bg-zinc-400'],
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset', $tone]) }}>
    <span @class(['size-1.5 rounded-full', $dot, 'animate-pulse' => $value === 'running'])></span>
    {{ str_replace('_', ' ', $value) }}
</span>
