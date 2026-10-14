<x-admin.layout :title="$workspace->name">
    <a href="{{ route('panel.workspaces.index') }}" class="text-sm text-zinc-500 hover:text-zinc-900">&larr; All workspaces</a>

    @if ($workspace->trashed())
        <p class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">Deleted {{ $workspace->deleted_at->toDayDateTimeString() }}.</p>
    @endif

    <section class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            'Plan' => $plan?->name ?? 'None',
            'Credits this period' => $usagePeriod ? number_format($usagePeriod->credits_used).' / '.($usagePeriod->credits_limit === null ? 'unlimited' : number_format($usagePeriod->credits_limit)) : 'No period yet',
            'Top-up credits' => number_format($workspace->topup_credits),
            'Workflows · Agents · Runs' => number_format($workspace->workflows_count).' · '.number_format($workspace->agents_count).' · '.number_format($workspace->runs_count),
        ] as $label => $value)
            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                <p class="text-xs text-zinc-500">{{ $label }}</p>
                <p class="mt-1 text-base font-semibold">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-admin.card title="Details">
            <dl class="space-y-3 px-5 py-4 text-sm">
                <div><dt class="text-zinc-500">ID</dt><dd class="font-mono text-xs break-all">{{ $workspace->id }}</dd></div>
                <div><dt class="text-zinc-500">Slug</dt><dd class="font-mono text-xs">{{ $workspace->slug }}</dd></div>
                <div>
                    <dt class="text-zinc-500">Owner</dt>
                    <dd>
                        @if ($workspace->owner)
                            <a href="{{ route('panel.users.show', $workspace->owner) }}" class="hover:underline">{{ $workspace->owner->email }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div><dt class="text-zinc-500">Created</dt><dd>{{ $workspace->created_at?->toDayDateTimeString() }}</dd></div>
                <div>
                    <dt class="text-zinc-500">Subscription</dt>
                    <dd>
                        @if ($subscription)
                            <x-admin.status-badge :status="$subscription->stripe_status" />
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
                <div><dt class="text-zinc-500">Credit overage</dt><dd>{{ $workspace->credit_overage_enabled ? 'On'.($workspace->credit_overage_limit !== null ? ', limit '.number_format($workspace->credit_overage_limit) : '') : 'Off' }}</dd></div>
                <div><dt class="text-zinc-500">Stripe customer</dt><dd class="font-mono text-xs">{{ $workspace->stripe_id ?? '—' }}</dd></div>
            </dl>
        </x-admin.card>

        <x-admin.card title="Members ({{ $workspace->members->count() }})" class="lg:col-span-2">
            <table class="w-full text-left text-sm">
                <thead class="text-xs text-zinc-500 uppercase">
                    <tr>
                        <th class="px-5 py-2 font-medium">User</th>
                        <th class="px-5 py-2 font-medium">Role</th>
                        <th class="px-5 py-2 font-medium">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($workspace->members as $member)
                        <tr>
                            <td class="px-5 py-2">
                                @if ($member->user)
                                    <a href="{{ route('panel.users.show', $member->user) }}" class="hover:underline">{{ $member->user->email }}</a>
                                @else
                                    <span class="text-zinc-500">Deleted user</span>
                                @endif
                            </td>
                            <td class="px-5 py-2">{{ $member->role?->value }}</td>
                            <td class="px-5 py-2 text-zinc-500">{{ $member->joined_at?->toFormattedDateString() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-admin.card>

        <x-admin.card title="Recent runs" class="lg:col-span-2">
            @include('admin.runs.partials.table', ['runs' => $recentRuns, 'showWorkspace' => false, 'empty' => 'No runs yet.'])
            <p class="border-t border-zinc-100 px-5 py-2 text-right text-xs">
                <a href="{{ route('panel.runs.index', ['workspace' => $workspace->id]) }}" class="text-zinc-500 hover:text-zinc-900">All runs for this workspace &rarr;</a>
            </p>
        </x-admin.card>

        <x-admin.card title="Recent credit charges">
            @if ($creditTransactions->isEmpty())
                <p class="px-5 py-6 text-sm text-zinc-500">No credits used yet.</p>
            @else
                <ul class="divide-y divide-zinc-100 text-sm">
                    @foreach ($creditTransactions as $transaction)
                        <li class="flex items-start justify-between gap-3 px-5 py-2">
                            <div>
                                <p>{{ str_replace('_', ' ', $transaction->source_type?->value ?? 'charge') }}</p>
                                <p class="text-xs text-zinc-500">
                                    {{ $transaction->created_at?->diffForHumans() }}
                                    @if ($transaction->topup_credits || $transaction->overage_credits)
                                        · {{ number_format($transaction->topup_credits) }} top-up, {{ number_format($transaction->overage_credits) }} overage
                                    @endif
                                </p>
                            </div>
                            <span class="font-mono tabular-nums">{{ number_format($transaction->credits) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-admin.card>
    </div>
</x-admin.layout>
