<x-super-admin.layout :title="$referral->referredUser?->email ?? 'Referral'" :description="'Referred by '.($referral->referrer?->email ?? 'a deleted user')" :back="route('super-admin.referrals.referrals.index')" back-label="Referrals">
    <x-slot:actions>
        <x-super-admin.status-badge :status="$referral->status" />
    </x-slot:actions>

    <x-super-admin.form-errors class="mb-6" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-super-admin.card title="Referral">
            <dl class="sa-dl">
                <div>
                    <dt>Referred user</dt>
                    <dd>
                        @if ($referral->referredUser)
                            <a href="{{ route('super-admin.users.show', $referral->referredUser) }}" class="sa-link">{{ $referral->referredUser->email }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Referrer</dt>
                    <dd>
                        @if ($referral->referrer)
                            <a href="{{ route('super-admin.users.show', $referral->referrer) }}" class="sa-link">{{ $referral->referrer->email }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div><dt>Code</dt><dd class="font-mono text-xs">{{ $referral->code?->code ?? '—' }}</dd></div>
                <div><dt>Program</dt><dd>{{ $referral->program?->name ?? '—' }}</dd></div>
                <div><dt>Signed up</dt><dd>{{ $referral->created_at?->toDayDateTimeString() }}</dd></div>
                <div><dt>Verified</dt><dd>{{ $referral->verified_at?->toDayDateTimeString() ?? '—' }}</dd></div>
                <div><dt>Activated</dt><dd>{{ $referral->activated_at?->toDayDateTimeString() ?? '—' }}</dd></div>
                <div><dt>Converted</dt><dd>{{ $referral->converted_at?->toDayDateTimeString() ?? '—' }}</dd></div>
                <div><dt>Payments</dt><dd class="tabular-nums">{{ $referral->payments_count }}</dd></div>
                @if ($referral->rejected_at)
                    <div><dt>Rejected</dt><dd>{{ $referral->rejected_at->toDayDateTimeString() }}</dd></div>
                    <div><dt>Reason</dt><dd>{{ $referral->rejection_reason }}</dd></div>
                @endif
            </dl>
        </x-super-admin.card>

        <x-super-admin.card title="Rewards" class="lg:col-span-2">
            @if ($referral->rewards->isEmpty())
                <x-super-admin.empty icon="gift">No rewards from this referral yet.</x-super-admin.empty>
            @else
                <ul class="divide-y divide-zinc-100 text-sm">
                    @foreach ($referral->rewards as $reward)
                        <li class="flex items-start justify-between gap-3 px-5 py-3">
                            <div>
                                <p class="font-medium">{{ $rewardSummaries[$reward->id] ?? str_replace('_', ' ', $reward->reward_type->value) }}</p>
                                <p class="text-xs text-zinc-500">To {{ $reward->recipient_role->value }} {{ $reward->recipient?->email }}</p>
                            </div>
                            <x-super-admin.status-badge :status="$reward->status" />
                        </li>
                    @endforeach
                </ul>
            @endif
            <x-slot:footer>
                <a href="{{ route('super-admin.referrals.rewards.index', ['recipient' => $referral->referrer_user_id]) }}" class="sa-link">All rewards for the referrer →</a>
            </x-slot:footer>
        </x-super-admin.card>

        @if ($referral->status === \App\Enums\Referrals\ReferralStatus::Rejected)
            <x-super-admin.card title="Restore referral" description="Puts it back in play. Withdrawn rewards are not re-granted automatically." class="lg:col-span-3">
                <form method="POST" action="{{ route('super-admin.referrals.referrals.restore', $referral) }}" class="flex flex-wrap items-end gap-3 px-5 py-4" data-confirm="Restore this referral?">
                    @csrf
                    <div class="min-w-56">
                        <label for="restore_password" class="sa-label">Your password</label>
                        <input id="restore_password" name="current_password" type="password" required autocomplete="current-password" class="sa-input">
                        @error('current_password', 'support')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="sa-btn-secondary">Restore</button>
                </form>
            </x-super-admin.card>
        @else
            <x-super-admin.card title="Reject referral" description="Marks it fraudulent or ineligible and claws back every reward it produced." class="lg:col-span-3">
                <form method="POST" action="{{ route('super-admin.referrals.referrals.reject', $referral) }}" class="flex flex-wrap items-end gap-3 px-5 py-4" data-confirm="Reject this referral and withdraw its rewards?">
                    @csrf
                    <div class="min-w-72 flex-1">
                        <label for="reject_reason" class="sa-label">Reason</label>
                        <input id="reject_reason" name="reason" type="text" required minlength="5" maxlength="255" class="sa-input">
                    </div>
                    <div class="min-w-56">
                        <label for="reject_password" class="sa-label">Your password</label>
                        <input id="reject_password" name="current_password" type="password" required autocomplete="current-password" class="sa-input">
                    </div>
                    <button type="submit" class="sa-btn-secondary text-red-700">Reject</button>
                    @if ($errors->reason->any())
                        <p class="w-full text-xs text-red-600">{{ $errors->reason->first() }}</p>
                    @endif
                </form>
            </x-super-admin.card>
        @endif
    </div>
</x-super-admin.layout>
