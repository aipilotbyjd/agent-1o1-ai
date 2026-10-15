<x-super-admin.layout title="Plans" description="The plan catalogue. Read-only: plans are seeded and kept in step with Stripe prices.">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($plans as $plan)
            <x-super-admin.card>
                <div class="px-5 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-zinc-900">{{ $plan->name }}</h2>
                            <p class="font-mono text-xs text-zinc-400">{{ $plan->slug }}</p>
                        </div>
                        <x-super-admin.status-badge :status="$plan->is_active ? 'active' : 'disabled'" />
                    </div>
                    @if ($plan->description)
                        <p class="mt-2 text-sm text-zinc-500">{{ $plan->description }}</p>
                    @endif
                </div>

                <dl class="sa-dl border-t border-zinc-100">
                    <div><dt>Monthly</dt><dd class="tabular-nums">${{ number_format($plan->price_monthly / 100, 2) }}</dd></div>
                    @if ($plan->price_yearly)
                        <div><dt>Yearly</dt><dd class="tabular-nums">${{ number_format($plan->price_yearly / 100, 2) }}</dd></div>
                    @endif
                    @if ($plan->price_lifetime)
                        <div><dt>Lifetime</dt><dd class="tabular-nums">${{ number_format($plan->price_lifetime / 100, 2) }}</dd></div>
                    @endif
                    <div><dt>Credits / month</dt><dd class="tabular-nums">{{ number_format($plan->creditsMonthly()) }}</dd></div>
                    <div><dt>Trial</dt><dd>{{ $plan->trial_days ? $plan->trial_days.' days' : 'None' }}</dd></div>
                    <div><dt>Active subscriptions</dt><dd class="tabular-nums">{{ number_format($plan->active_subscriptions_count) }}</dd></div>
                    <div><dt>Active grants</dt><dd class="tabular-nums">{{ number_format($plan->active_grants_count) }}</dd></div>
                </dl>

                @if ($plan->limits)
                    <div class="border-t border-zinc-100 px-5 py-4">
                        <p class="text-xs font-semibold tracking-wider text-zinc-500 uppercase">Limits</p>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($plan->limits as $limit => $value)
                                <li class="flex justify-between gap-3">
                                    <span class="text-zinc-500">{{ str_replace('_', ' ', $limit) }}</span>
                                    <span class="tabular-nums">{{ $value === null || (int) $value < 0 ? 'Unlimited' : number_format((int) $value) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php $enabledFeatures = array_keys(array_filter($plan->features ?? [])); @endphp

                @if ($enabledFeatures)
                    <div class="border-t border-zinc-100 px-5 py-4">
                        <p class="text-xs font-semibold tracking-wider text-zinc-500 uppercase">Features</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($enabledFeatures as $feature)
                                <span class="rounded-md bg-zinc-100 px-2 py-0.5 text-xs text-zinc-700">{{ str_replace('_', ' ', $feature) }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-super-admin.card>
        @empty
            <x-super-admin.card class="md:col-span-2 xl:col-span-3">
                <x-super-admin.empty icon="tag">No plans yet. Run the plan seeder.</x-super-admin.empty>
            </x-super-admin.card>
        @endforelse
    </div>
</x-super-admin.layout>
