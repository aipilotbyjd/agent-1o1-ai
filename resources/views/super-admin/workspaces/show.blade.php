<x-super-admin.layout :title="$workspace->name" :description="$workspace->slug" :back="route('super-admin.workspaces.index')" back-label="All workspaces">
    <x-slot:actions>
        @if ($subscription)
            <x-super-admin.status-badge :status="$subscription->stripe_status" />
        @endif
        <a href="{{ route('super-admin.runs.index', ['workspace' => $workspace->id]) }}" class="sa-btn-secondary">
            <x-super-admin.icon name="play" class="size-4" />
            View runs
        </a>
    </x-slot:actions>

    @if ($workspace->trashed())
        <div class="mb-6 flex items-center gap-2.5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-600/15">
            <x-super-admin.icon name="warning" class="size-5 text-red-600" />
            Deleted {{ $workspace->deleted_at->toDayDateTimeString() }}.
        </div>
    @endif

    @php
        $creditsUsed = $usagePeriod?->credits_used;
        $creditsLimit = $usagePeriod?->credits_limit;
        $usageFraction = $usagePeriod && $creditsLimit ? min(1, $creditsUsed / max(1, $creditsLimit)) : null;
    @endphp

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-super-admin.stat label="Plan">{{ $plan?->name ?? 'None' }}</x-super-admin.stat>
        <x-super-admin.stat label="Credits this period">
            @if ($usagePeriod)
                {{ number_format($creditsUsed) }} <span class="text-sm font-normal text-zinc-500">/ {{ $creditsLimit === null ? 'unlimited' : number_format($creditsLimit) }}</span>
            @else
                <span class="text-zinc-400">No period yet</span>
            @endif
            <x-slot:footer>
            @if ($usageFraction !== null)
                <progress value="{{ round($usageFraction * 100) }}" max="100" @class([
                    'mt-3 h-1.5 w-full overflow-hidden rounded-full [&::-webkit-progress-bar]:bg-zinc-100 [&::-moz-progress-bar]:bg-indigo-500',
                    '[&::-webkit-progress-value]:bg-indigo-500' => $usageFraction < 0.9,
                    '[&::-webkit-progress-value]:bg-critical' => $usageFraction >= 0.9,
                ])>{{ round($usageFraction * 100) }}%</progress>
            @endif
            </x-slot:footer>
        </x-super-admin.stat>
        <x-super-admin.stat label="Top-up credits">{{ number_format($workspace->topup_credits) }}</x-super-admin.stat>
        <x-super-admin.stat label="Workflows · Agents · Runs">{{ number_format($workspace->workflows_count) }} <span class="text-zinc-300">·</span> {{ number_format($workspace->agents_count) }} <span class="text-zinc-300">·</span> {{ number_format($workspace->runs_count) }}</x-super-admin.stat>
    </section>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-super-admin.card title="Details">
            <dl class="sa-dl">
                <div><dt>ID</dt><dd class="font-mono text-xs break-all">{{ $workspace->id }} <x-super-admin.copy :value="$workspace->id" label="Copy workspace ID" /></dd></div>
                <div><dt>Slug</dt><dd class="font-mono text-xs">{{ $workspace->slug }}</dd></div>
                <div>
                    <dt>Owner</dt>
                    <dd>
                        @if ($workspace->owner)
                            <a href="{{ route('super-admin.users.show', $workspace->owner) }}" class="sa-link">{{ $workspace->owner->email }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div><dt>Created</dt><dd>{{ $workspace->created_at?->toDayDateTimeString() }}</dd></div>
                <div>
                    <dt>Subscription</dt>
                    <dd>
                        @if ($subscription)
                            <x-super-admin.status-badge :status="$subscription->stripe_status" />
                            <span class="ml-1">{{ $subscription->plan?->name }}</span>
                            @if ($subscription->ends_at)
                                <span class="block text-xs text-zinc-500">Ends {{ $subscription->ends_at->toFormattedDateString() }}</span>
                            @elseif ($subscription->trial_ends_at?->isFuture())
                                <span class="block text-xs text-zinc-500">Trial ends {{ $subscription->trial_ends_at->toFormattedDateString() }}</span>
                            @endif
                        @else
                            None
                        @endif
                    </dd>
                </div>
                <div><dt>Credit overage</dt><dd>{{ $workspace->credit_overage_enabled ? 'On'.($workspace->credit_overage_limit !== null ? ', limit '.number_format($workspace->credit_overage_limit) : '') : 'Off' }}</dd></div>
                <div><dt>Stripe customer</dt><dd class="font-mono text-xs">{{ $workspace->stripe_id ?? '—' }}</dd></div>
            </dl>
        </x-super-admin.card>

        <x-super-admin.card title="Members" :description="$workspace->members->count().' '.str('member')->plural($workspace->members->count())" class="lg:col-span-2">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th class="text-right">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($workspace->members as $member)
                        <tr>
                            <td>
                                @if ($member->user)
                                    <div class="flex items-center gap-3">
                                        <x-super-admin.avatar :name="$member->user->name ?: $member->user->email" size="size-7" />
                                        <a href="{{ route('super-admin.users.show', $member->user) }}" class="sa-row-link font-normal">{{ $member->user->email }}</a>
                                    </div>
                                @else
                                    <span class="text-zinc-500">Deleted user</span>
                                @endif
                            </td>
                            <td><span class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-xs font-medium text-zinc-600 capitalize">{{ $member->role?->value }}</span></td>
                            <td class="text-right text-zinc-500">{{ $member->joined_at?->toFormattedDateString() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-super-admin.card>

        <x-super-admin.card title="Recent runs" class="lg:col-span-2">
            <x-slot:actions>
                <a href="{{ route('super-admin.runs.index', ['workspace' => $workspace->id]) }}" class="inline-flex items-center gap-0.5 font-medium text-zinc-500 hover:text-zinc-900">All runs for this workspace<x-super-admin.icon name="chevron-right" class="size-3.5" /></a>
            </x-slot:actions>
            @include('super-admin.runs.partials.table', ['runs' => $recentRuns, 'showWorkspace' => false, 'empty' => 'No runs yet.'])
        </x-super-admin.card>

        <x-super-admin.card title="Recent credit charges">
            @if ($creditTransactions->isEmpty())
                <x-super-admin.empty icon="card">No credits used yet.</x-super-admin.empty>
            @else
                <ul class="divide-y divide-zinc-100 text-sm">
                    @foreach ($creditTransactions as $transaction)
                        <li class="flex items-start justify-between gap-3 px-5 py-3">
                            <div>
                                <p class="text-zinc-900 capitalize">{{ str_replace('_', ' ', $transaction->source_type?->value ?? 'charge') }}</p>
                                <p class="text-xs text-zinc-500">
                                    {{ $transaction->created_at?->diffForHumans() }}
                                    @if ($transaction->topup_credits || $transaction->overage_credits)
                                        · {{ number_format($transaction->topup_credits) }} top-up, {{ number_format($transaction->overage_credits) }} overage
                                    @endif
                                </p>
                            </div>
                            <span class="font-mono font-medium text-zinc-900 tabular-nums">{{ number_format($transaction->credits) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-super-admin.card>
    </div>

    @include('super-admin.workspaces.partials.billing-actions')
</x-super-admin.layout>
