<x-super-admin.layout :title="$program->name" :description="$program->description ?: $program->slug" :back="route('super-admin.referrals.programs.index')" back-label="Programs">
    <x-slot:actions>
        @if ($program->is_default)
            <span class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">Default program</span>
        @else
            <form method="POST" action="{{ route('super-admin.referrals.programs.make-default', $program) }}" data-confirm="Make {{ $program->name }} the default program for new signups?">
                @csrf
                <button type="submit" class="sa-btn-secondary">Make default</button>
            </form>
        @endif
        <form method="POST" action="{{ route('super-admin.referrals.programs.duplicate', $program) }}">
            @csrf
            <button type="submit" class="sa-btn-secondary">Duplicate</button>
        </form>
        <a href="{{ route('super-admin.referrals.programs.edit', $program) }}" class="sa-btn-primary">Edit settings</a>
    </x-slot:actions>

    <x-super-admin.form-errors class="mb-6" />

    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            'Status' => $program->isLive() ? 'Live' : 'Not live',
            'Approval' => ucfirst($program->approval_mode?->value ?? '—'),
            'Attribution' => $program->attribution_window_days.' days',
            'Referrals' => number_format($program->referrals_count),
        ] as $label => $value)
            <div class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-zinc-200/80">
                <p class="text-sm font-medium text-zinc-500">{{ $label }}</p>
                <p class="mt-2 text-lg font-semibold tracking-tight">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <x-super-admin.card title="Reward rules" description="Checked in this order for every referral event" class="mt-6">
        <x-slot:actions>
            <a href="{{ route('super-admin.referrals.programs.rules.create', $program) }}" class="sa-btn-primary">Add rule</a>
        </x-slot:actions>

        @if ($program->rules->isEmpty())
            <x-super-admin.empty icon="gift">No rules yet, so this program gives no rewards.</x-super-admin.empty>
        @else
            <ul class="divide-y divide-zinc-100">
                @foreach ($program->rules as $rule)
                    <li class="flex flex-wrap items-start justify-between gap-4 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-zinc-900">{{ $rule->name }}</p>
                                <x-super-admin.status-badge :status="$rule->is_active ? 'active' : 'off'" />
                            </div>
                            <p class="mt-1 text-sm text-zinc-600">{{ $descriptions[$rule->id] ?? '' }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            @foreach (['up' => 'arrow-up', 'down' => 'arrow-down'] as $direction => $icon)
                                <form method="POST" action="{{ route('super-admin.referrals.rules.move', $rule) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="{{ $direction }}">
                                    <button type="submit" class="rounded-md p-1.5 text-zinc-500 ring-1 ring-zinc-200 hover:bg-zinc-50 disabled:opacity-30" @disabled(($direction === 'up' && $loop->parent->first) || ($direction === 'down' && $loop->parent->last)) title="Move {{ $direction }}">
                                        <x-super-admin.icon :name="$icon" class="size-4" />
                                    </button>
                                </form>
                            @endforeach
                            <form method="POST" action="{{ route('super-admin.referrals.rules.toggle', $rule) }}">
                                @csrf
                                <button type="submit" class="sa-btn-secondary">{{ $rule->is_active ? 'Switch off' : 'Switch on' }}</button>
                            </form>
                            <a href="{{ route('super-admin.referrals.rules.edit', $rule) }}" class="sa-btn-secondary">Edit</a>
                            <form method="POST" action="{{ route('super-admin.referrals.rules.destroy', $rule) }}" data-confirm="Delete the rule “{{ $rule->name }}”? Rewards it already gave are kept.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sa-btn-secondary text-red-700">Delete</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-super-admin.card>

    @unless ($program->is_default)
        <x-super-admin.card title="Delete program" description="Existing referrals keep their history." class="mt-6">
            <form method="POST" action="{{ route('super-admin.referrals.programs.destroy', $program) }}" class="px-5 py-4" data-confirm="Delete the program {{ $program->name }}?">
                @csrf
                @method('DELETE')
                <button type="submit" class="sa-btn-secondary text-red-700">Delete program</button>
            </form>
        </x-super-admin.card>
    @endunless
</x-super-admin.layout>
