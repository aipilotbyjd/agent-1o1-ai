<x-super-admin.layout title="Referral rewards" description="Every action here moves credits, plan time or money and needs your password">
    <x-slot:actions>
        <a href="{{ route('super-admin.referrals.rewards.create') }}" class="sa-btn-primary">Goodwill reward</a>
    </x-slot:actions>

    <x-super-admin.filters :action="route('super-admin.referrals.rewards.index')" :active="(bool) array_filter($filters)">
        <x-super-admin.filter-select name="status" label="Status" :value="$filters['status'] ?? null" :options="\App\Enums\Referrals\ReferralRewardStatus::class" />
        <x-super-admin.filter-select name="reward_type" label="Type" :value="$filters['reward_type'] ?? null" :options="\App\Enums\Referrals\ReferralRewardType::class" />
        @if ($filters['recipient'] ?? null)
            <input type="hidden" name="recipient" value="{{ $filters['recipient'] }}">
        @endif
    </x-super-admin.filters>

    <x-super-admin.form-errors class="mt-6" />
    <x-super-admin.form-errors bag="reason" class="mt-6" />
    <x-super-admin.form-errors bag="support" class="mt-6" />

    <x-super-admin.card class="mt-6">
        @if ($rewards->isEmpty())
            <x-super-admin.empty :clear="array_filter($filters) ? route('super-admin.referrals.rewards.index') : null" icon="coins">No rewards match.</x-super-admin.empty>
        @else
            <ul class="divide-y divide-zinc-100">
                @foreach ($rewards as $reward)
                    <li class="flex flex-wrap items-start justify-between gap-4 px-5 py-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-zinc-900">{{ $summaries[$reward->id] ?? str_replace('_', ' ', $reward->reward_type->value) }}</p>
                                <x-super-admin.status-badge :status="$reward->status" />
                            </div>
                            <p class="mt-1 text-xs text-zinc-500">
                                {{ ucfirst($reward->recipient_role->value) }}
                                @if ($reward->recipient)
                                    <a href="{{ route('super-admin.users.show', $reward->recipient) }}" class="sa-link">{{ $reward->recipient->email }}</a>
                                @endif
                                @if ($reward->workspace)
                                    · {{ $reward->workspace->name }}
                                @endif
                                · {{ $reward->created_at?->diffForHumans() }}
                                @if ($reward->grant_after && $reward->status->value === 'pending')
                                    · grants {{ $reward->grant_after->toFormattedDateString() }}
                                @endif
                                @if ($reward->revoked_reason)
                                    · revoked: {{ $reward->revoked_reason }}
                                @endif
                            </p>
                        </div>

                        @if (in_array($reward->status->value, ['awaiting_approval', 'pending', 'granted'], true))
                            <details class="text-left">
                                <summary class="sa-btn-secondary cursor-pointer list-none">Actions</summary>
                                <div class="mt-2 w-80 space-y-3 rounded-xl bg-white p-3 shadow-lg ring-1 ring-zinc-200">
                                    @if (in_array($reward->status->value, ['awaiting_approval', 'pending'], true))
                                        <form method="POST" class="space-y-2">
                                            @csrf
                                            <input name="current_password" type="password" required autocomplete="current-password" placeholder="Your password" class="sa-input">
                                            <div class="flex gap-2">
                                                @if ($reward->status->value === 'awaiting_approval')
                                                    <button type="submit" formaction="{{ route('super-admin.referrals.rewards.approve', $reward) }}" class="sa-btn-primary flex-1" data-confirm="Approve this reward?">Approve</button>
                                                @endif
                                                <button type="submit" formaction="{{ route('super-admin.referrals.rewards.grant-now', $reward) }}" class="sa-btn-secondary flex-1" data-confirm="Grant this reward now, skipping its hold?">Grant now</button>
                                            </div>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('super-admin.referrals.rewards.revoke', $reward) }}" class="space-y-2 border-t border-zinc-100 pt-3" data-confirm="Revoke this reward? Anything already granted is clawed back where possible.">
                                        @csrf
                                        <input name="reason" type="text" required minlength="5" maxlength="255" placeholder="Reason for revoking" class="sa-input">
                                        <input name="current_password" type="password" required autocomplete="current-password" placeholder="Your password" class="sa-input">
                                        <button type="submit" class="sa-btn-secondary w-full text-red-700">Revoke</button>
                                    </form>
                                </div>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-super-admin.card>

    <x-super-admin.pagination :paginator="$rewards" />
</x-super-admin.layout>
