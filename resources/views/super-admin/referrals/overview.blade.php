<x-super-admin.layout title="Referrals" :description="'Last '.$days.' days'.($defaultProgram ? ' · default program: '.$defaultProgram->name : ' · no default program')">
    <x-slot:actions>
        @foreach ([7, 30, 90, 365] as $window)
            <a href="{{ route('super-admin.referrals.overview', ['days' => $window]) }}" @class(['sa-btn-secondary', 'ring-indigo-500 text-indigo-700' => $days === $window])>{{ $window }}d</a>
        @endforeach
    </x-slot:actions>

    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            'Visits' => number_format($stats['visits']),
            'Signups' => number_format($stats['signups']),
            'Activation rate' => number_format($stats['activation_rate'] * 100, 1).'%',
            'Conversion rate' => number_format($stats['conversion_rate'] * 100, 1).'%',
            'Credits issued' => number_format($stats['credits_issued']),
            'Plan days issued' => number_format($stats['plan_days_issued']),
            'Invoice credit issued' => '$'.number_format($stats['invoice_credit_cents_issued'] / 100, 2),
            'Credits clawed back' => number_format($stats['credits_clawed_back']),
        ] as $label => $value)
            <div class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-zinc-200/80">
                <p class="text-sm font-medium text-zinc-500">{{ $label }}</p>
                <p class="mt-2 text-xl font-semibold tracking-tight tabular-nums">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-super-admin.card title="Needs attention">
            <dl class="sa-dl">
                <div>
                    <dt>Awaiting approval</dt>
                    <dd><a href="{{ route('super-admin.referrals.rewards.index', ['status' => 'awaiting_approval']) }}" class="sa-link tabular-nums">{{ number_format($stats['rewards_awaiting_approval']) }}</a></dd>
                </div>
                <div>
                    <dt>On hold (pending)</dt>
                    <dd><a href="{{ route('super-admin.referrals.rewards.index', ['status' => 'pending']) }}" class="sa-link tabular-nums">{{ number_format($stats['rewards_pending']) }}</a></dd>
                </div>
                <div>
                    <dt>Rejected referrals</dt>
                    <dd><a href="{{ route('super-admin.referrals.referrals.index', ['status' => 'rejected']) }}" class="sa-link tabular-nums">{{ number_format($stats['rejected']) }}</a></dd>
                </div>
                <div><dt>Active referral plan grants</dt><dd class="tabular-nums">{{ number_format($stats['active_referral_plan_grants']) }}</dd></div>
            </dl>
        </x-super-admin.card>

        <x-super-admin.card title="Referrals by status">
            <dl class="sa-dl">
                @foreach (\App\Enums\Referrals\ReferralStatus::cases() as $status)
                    <div>
                        <dt><x-super-admin.status-badge :status="$status" /></dt>
                        <dd class="tabular-nums">{{ number_format((int) ($stats['by_status'][$status->value] ?? 0)) }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-super-admin.card>

        <x-super-admin.card title="Top referrers" description="By converted referrals">
            @if (count($stats['top_referrers']) === 0)
                <x-super-admin.empty icon="user">No conversions in this window.</x-super-admin.empty>
            @else
                <ul class="divide-y divide-zinc-100 text-sm">
                    @foreach ($stats['top_referrers'] as $referrer)
                        <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                            <a href="{{ route('super-admin.users.show', $referrer['user_id']) }}" class="sa-link truncate">{{ $referrer['email'] }}</a>
                            <span class="font-semibold tabular-nums">{{ $referrer['converted'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-super-admin.card>
    </div>
</x-super-admin.layout>
