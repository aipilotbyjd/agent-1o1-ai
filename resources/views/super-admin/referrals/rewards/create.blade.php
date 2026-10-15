<x-super-admin.layout title="Goodwill reward" description="A reward outside any referral, e.g. to make up for an outage" :back="route('super-admin.referrals.rewards.index')" back-label="Rewards">
    @if (! $recipient)
        <x-super-admin.card title="Who is it for?">
            <form method="GET" action="{{ route('super-admin.referrals.rewards.create') }}" class="flex flex-wrap items-end gap-3 px-5 py-4">
                <div class="min-w-72 flex-1">
                    <label for="user" class="sa-label">User ID</label>
                    <input id="user" name="user" type="text" required class="sa-input font-mono" placeholder="Copy it from the user's page">
                </div>
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
                    <div>
                        <label for="workspace_id" class="sa-label">Workspace</label>
                        <select id="workspace_id" name="workspace_id" class="sa-input pr-8">
                            <option value="">Their current workspace</option>
                            @foreach ($recipient->workspaces as $workspace)
                                <option value="{{ $workspace->id }}" @selected(old('workspace_id') === $workspace->id)>{{ $workspace->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="reward_type" class="sa-label">Type</label>
                        <select id="reward_type" name="reward_type" required class="sa-input pr-8">
                            @foreach (\App\Enums\Referrals\ReferralRewardType::cases() as $type)
                                <option value="{{ $type->value }}" @selected(old('reward_type') === $type->value)>{{ str_replace('_', ' ', $type->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="credits" class="sa-label">Credits</label>
                        <input id="credits" name="credits" type="number" min="1" value="{{ old('credits') }}" class="sa-input">
                    </div>
                    <div>
                        <label for="plan_id" class="sa-label">Plan (plan time)</label>
                        <select id="plan_id" name="plan_id" class="sa-input pr-8">
                            <option value="">—</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected(old('plan_id') === $plan->id)>{{ $plan->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="duration_days" class="sa-label">Plan days</label>
                        <input id="duration_days" name="duration_days" type="number" min="1" max="3650" value="{{ old('duration_days') }}" class="sa-input">
                    </div>
                    <div>
                        <label for="amount_cents" class="sa-label">Invoice credit (cents)</label>
                        <input id="amount_cents" name="amount_cents" type="number" min="1" value="{{ old('amount_cents') }}" class="sa-input">
                    </div>
                    <div>
                        <label for="trial_days" class="sa-label">Trial extension days</label>
                        <input id="trial_days" name="trial_days" type="number" min="1" max="365" value="{{ old('trial_days') }}" class="sa-input">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="notes" class="sa-label">Notes</label>
                        <input id="notes" name="notes" type="text" maxlength="1000" value="{{ old('notes') }}" class="sa-input">
                    </div>
                    <div>
                        <label for="current_password" class="sa-label">Your password</label>
                        <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="sa-input">
                    </div>
                </div>
            </x-super-admin.card>

            <div class="flex justify-end">
                <button type="submit" class="sa-btn-primary">Grant reward</button>
            </div>
        </form>
    @endif
</x-super-admin.layout>
