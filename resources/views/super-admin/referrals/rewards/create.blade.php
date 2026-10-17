<x-super-admin.layout title="Goodwill reward" description="A reward outside any referral, e.g. to make up for an outage" :back="route('super-admin.referrals.rewards.index')" back-label="Rewards">
    @if (! $recipient)
        <x-super-admin.card title="Who is it for?">
            <form method="GET" action="{{ route('super-admin.referrals.rewards.create') }}" class="flex flex-wrap items-end gap-3 px-5 py-4">
                <x-super-admin.field for="user" label="User ID" hint="Copy it from the user's page." class="min-w-72 flex-1">
                    <input id="user" name="user" type="text" required class="sa-input font-mono" placeholder="9f1c…">
                </x-super-admin.field>
                <button type="submit" class="sa-btn-primary">Continue</button>
            </form>
        </x-super-admin.card>
    @else
        <form method="POST" action="{{ route('super-admin.referrals.rewards.store') }}" class="space-y-6" data-confirm="Grant this reward to {{ $recipient->email }}?">
            @csrf
            <input type="hidden" name="user_id" value="{{ $recipient->id }}">

            <x-super-admin.form-errors />

            <x-super-admin.card :title="'For '.$recipient->email">
                <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                    <x-super-admin.field for="workspace_id" label="Workspace">
                        <select id="workspace_id" name="workspace_id" class="sa-input pr-8">
                            <option value="">Their current workspace</option>
                            @foreach ($recipient->workspaces as $workspace)
                                <option value="{{ $workspace->id }}" @selected(old('workspace_id') === $workspace->id)>{{ $workspace->name }}</option>
                            @endforeach
                        </select>
                    </x-super-admin.field>
                    <x-super-admin.field for="reward_type" label="Type">
                        <select id="reward_type" name="reward_type" required class="sa-input pr-8">
                            @foreach (\App\Enums\Referrals\ReferralRewardType::cases() as $type)
                                <option value="{{ $type->value }}" @selected(old('reward_type') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </x-super-admin.field>
                    <x-super-admin.field for="credits" label="Credits" data-show-when="reward_type:credits">
                        <input id="credits" name="credits" type="number" min="1" value="{{ old('credits') }}" class="sa-input">
                    </x-super-admin.field>
                    <x-super-admin.field for="plan_id" label="Plan" data-show-when="reward_type:plan_time">
                        <select id="plan_id" name="plan_id" class="sa-input pr-8">
                            <option value="">—</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected(old('plan_id') === $plan->id)>{{ $plan->name }}</option>
                            @endforeach
                        </select>
                    </x-super-admin.field>
                    <x-super-admin.field for="duration_days" label="Plan days" data-show-when="reward_type:plan_time">
                        <input id="duration_days" name="duration_days" type="number" min="1" max="3650" value="{{ old('duration_days') }}" class="sa-input">
                    </x-super-admin.field>
                    <x-super-admin.field for="amount_cents" label="Invoice credit (cents)" data-show-when="reward_type:stripe_balance_credit">
                        <input id="amount_cents" name="amount_cents" type="number" min="1" value="{{ old('amount_cents') }}" class="sa-input">
                    </x-super-admin.field>
                    <x-super-admin.field for="trial_days" label="Trial extension days" data-show-when="reward_type:trial_extension">
                        <input id="trial_days" name="trial_days" type="number" min="1" max="365" value="{{ old('trial_days') }}" class="sa-input">
                    </x-super-admin.field>
                    <x-super-admin.field for="notes" label="Notes" hint="Why you're granting it." class="sm:col-span-2">
                        <input id="notes" name="notes" type="text" maxlength="1000" value="{{ old('notes') }}" class="sa-input">
                    </x-super-admin.field>
                    <x-super-admin.field for="current_password" label="Your password">
                        <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="sa-input">
                    </x-super-admin.field>
                </div>
            </x-super-admin.card>

            <x-super-admin.form-actions :cancel="route('super-admin.referrals.rewards.index')">
                <button type="submit" class="sa-btn-primary">Grant reward</button>
            </x-super-admin.form-actions>
        </form>
    @endif
</x-super-admin.layout>
