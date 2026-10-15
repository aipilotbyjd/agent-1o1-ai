@php
    $editing = $program->exists;
    $value = fn (string $field, mixed $default = null) => old($field, $program->{$field} ?? $default);
    $enumValue = fn (string $field) => old($field, $program->{$field} instanceof \BackedEnum ? $program->{$field}->value : $program->{$field});
    $date = fn (string $field) => old($field, $program->{$field}?->format('Y-m-d\TH:i'));
    $checked = fn (string $field) => (bool) old($field, $program->{$field});
    $fraud = fn (string $key) => old('fraud_checks.'.$key, data_get($program->fraud_checks ?? [], $key, data_get(\App\Models\Referrals\ReferralProgram::DEFAULT_FRAUD_CHECKS, $key)));
    $eligiblePlans = old('referrer_eligible_plan_ids', $program->referrer_eligible_plan_ids ?? []);
@endphp

<x-super-admin.layout :title="$editing ? 'Edit '.$program->name : 'New referral program'" :back="$editing ? route('super-admin.referrals.programs.show', $program) : route('super-admin.referrals.programs.index')" :back-label="$editing ? $program->name : 'Programs'">
    <form method="POST" action="{{ $editing ? route('super-admin.referrals.programs.update', $program) : route('super-admin.referrals.programs.store') }}" class="space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-super-admin.form-errors />

        <x-super-admin.card title="Program">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="sa-label">Name</label>
                    <input id="name" name="name" type="text" required maxlength="255" value="{{ $value('name') }}" class="sa-input">
                </div>
                <div>
                    <label for="slug" class="sa-label">Slug {{ $editing ? '' : '(blank to generate)' }}</label>
                    <input id="slug" name="slug" type="text" maxlength="255" value="{{ $value('slug') }}" class="sa-input font-mono">
                </div>
                <div class="sm:col-span-2">
                    <label for="description" class="sa-label">Description</label>
                    <textarea id="description" name="description" rows="2" maxlength="2000" class="sa-input">{{ $value('description') }}</textarea>
                </div>
                <div>
                    <label for="starts_at" class="sa-label">Starts</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" value="{{ $date('starts_at') }}" class="sa-input">
                </div>
                <div>
                    <label for="ends_at" class="sa-label">Ends</label>
                    <input id="ends_at" name="ends_at" type="datetime-local" value="{{ $date('ends_at') }}" class="sa-input">
                </div>
                <div class="flex flex-wrap gap-6 sm:col-span-2">
                    @foreach (['is_active' => 'Active', 'require_verified_email' => 'Require a verified email', 'revoke_on_partial_refund' => 'Revoke rewards on a partial refund'] as $field => $label)
                        <label class="flex items-center gap-2 text-sm text-zinc-700">
                            <input type="hidden" name="{{ $field }}" value="0">
                            <input type="checkbox" name="{{ $field }}" value="1" class="size-4 rounded border-zinc-300 accent-zinc-900" @checked($checked($field))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
        </x-super-admin.card>

        <x-super-admin.card title="Attribution and activation">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                <div>
                    <label for="attribution_window_days" class="sa-label">Attribution window (days)</label>
                    <input id="attribution_window_days" name="attribution_window_days" type="number" min="1" max="365" value="{{ $value('attribution_window_days') }}" class="sa-input">
                </div>
                <div>
                    <label for="claim_window_hours" class="sa-label">Claim window (hours)</label>
                    <input id="claim_window_hours" name="claim_window_hours" type="number" min="1" max="720" value="{{ $value('claim_window_hours') }}" class="sa-input">
                </div>
                <div>
                    <label for="activation_event" class="sa-label">Activation event</label>
                    <select id="activation_event" name="activation_event" class="sa-input pr-8">
                        @foreach (\App\Enums\Referrals\ReferralActivationEvent::cases() as $event)
                            <option value="{{ $event->value }}" @selected($enumValue('activation_event') === $event->value)>{{ str_replace('_', ' ', $event->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="activation_min_count" class="sa-label">Activation count</label>
                    <input id="activation_min_count" name="activation_min_count" type="number" min="1" max="1000" value="{{ $value('activation_min_count') }}" class="sa-input">
                </div>
                <div>
                    <label for="activation_window_days" class="sa-label">Activation window (days)</label>
                    <input id="activation_window_days" name="activation_window_days" type="number" min="1" max="365" value="{{ $value('activation_window_days') }}" class="sa-input">
                </div>
            </div>
        </x-super-admin.card>

        <x-super-admin.card title="Rewards and limits">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                <div>
                    <label for="approval_mode" class="sa-label">Approval</label>
                    <select id="approval_mode" name="approval_mode" class="sa-input pr-8">
                        @foreach (\App\Enums\Referrals\ReferralApprovalMode::cases() as $mode)
                            <option value="{{ $mode->value }}" @selected($enumValue('approval_mode') === $mode->value)>{{ $mode->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="default_hold_days" class="sa-label">Default hold (days)</label>
                    <input id="default_hold_days" name="default_hold_days" type="number" min="0" max="365" value="{{ $value('default_hold_days') }}" class="sa-input">
                </div>
                <div>
                    <label for="referrer_min_account_age_days" class="sa-label">Referrer min account age (days)</label>
                    <input id="referrer_min_account_age_days" name="referrer_min_account_age_days" type="number" min="0" max="3650" value="{{ $value('referrer_min_account_age_days') }}" class="sa-input">
                </div>
                <div>
                    <label for="referrer_monthly_credit_cap" class="sa-label">Referrer credits / month (blank = no cap)</label>
                    <input id="referrer_monthly_credit_cap" name="referrer_monthly_credit_cap" type="number" min="0" value="{{ $value('referrer_monthly_credit_cap') }}" class="sa-input">
                </div>
                <div>
                    <label for="referrer_max_stacked_plan_days" class="sa-label">Max stacked plan days (blank = no cap)</label>
                    <input id="referrer_max_stacked_plan_days" name="referrer_max_stacked_plan_days" type="number" min="0" max="36500" value="{{ $value('referrer_max_stacked_plan_days') }}" class="sa-input">
                </div>
                <div>
                    <label for="referrer_max_referrals_per_month" class="sa-label">Max referrals / month (blank = no cap)</label>
                    <input id="referrer_max_referrals_per_month" name="referrer_max_referrals_per_month" type="number" min="1" max="100000" value="{{ $value('referrer_max_referrals_per_month') }}" class="sa-input">
                </div>
                <fieldset class="sm:col-span-3">
                    <legend class="sa-label">Referrers must be on (none ticked = any plan)</legend>
                    <input type="hidden" name="referrer_eligible_plan_ids" value="">
                    <div class="mt-2 flex flex-wrap gap-4">
                        @foreach ($plans as $plan)
                            <label class="flex items-center gap-2 text-sm text-zinc-700">
                                <input type="checkbox" name="referrer_eligible_plan_ids[]" value="{{ $plan->id }}" class="size-4 rounded border-zinc-300 accent-zinc-900" @checked(in_array($plan->id, $eligiblePlans ?? [], true))>
                                {{ $plan->name }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </div>
        </x-super-admin.card>

        <x-super-admin.card title="Fraud checks">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-2">
                @foreach (['shared_workspace' => 'Reject if referrer and referee share a workspace', 'same_email_domain' => 'Reject if both use the same email domain', 'disposable_email' => 'Reject disposable email addresses', 'card_fingerprint' => 'Reject a reused payment card'] as $key => $label)
                    <label class="flex items-center gap-2 text-sm text-zinc-700">
                        <input type="hidden" name="fraud_checks[{{ $key }}]" value="0">
                        <input type="checkbox" name="fraud_checks[{{ $key }}]" value="1" class="size-4 rounded border-zinc-300 accent-zinc-900" @checked((bool) $fraud($key))>
                        {{ $label }}
                    </label>
                @endforeach

                <div class="rounded-xl bg-zinc-50 p-3 ring-1 ring-zinc-200/80">
                    <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                        <input type="hidden" name="fraud_checks[ip_velocity][enabled]" value="0">
                        <input type="checkbox" name="fraud_checks[ip_velocity][enabled]" value="1" class="size-4 rounded border-zinc-300 accent-zinc-900" @checked((bool) $fraud('ip_velocity.enabled'))>
                        Limit signups per IP
                    </label>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <div>
                            <label for="ip_velocity_max" class="sa-label">Max signups</label>
                            <input id="ip_velocity_max" name="fraud_checks[ip_velocity][max]" type="number" min="1" max="10000" value="{{ $fraud('ip_velocity.max') }}" class="sa-input">
                        </div>
                        <div>
                            <label for="ip_velocity_hours" class="sa-label">Per hours</label>
                            <input id="ip_velocity_hours" name="fraud_checks[ip_velocity][hours]" type="number" min="1" max="720" value="{{ $fraud('ip_velocity.hours') }}" class="sa-input">
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-zinc-50 p-3 ring-1 ring-zinc-200/80">
                    <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                        <input type="hidden" name="fraud_checks[velocity_alert][enabled]" value="0">
                        <input type="checkbox" name="fraud_checks[velocity_alert][enabled]" value="1" class="size-4 rounded border-zinc-300 accent-zinc-900" @checked((bool) $fraud('velocity_alert.enabled'))>
                        Alert on a signup spike
                    </label>
                    <div class="mt-2">
                        <label for="velocity_alert_max" class="sa-label">Signups per hour</label>
                        <input id="velocity_alert_max" name="fraud_checks[velocity_alert][max_per_hour]" type="number" min="1" max="100000" value="{{ $fraud('velocity_alert.max_per_hour') }}" class="sa-input">
                    </div>
                </div>
            </div>
        </x-super-admin.card>

        <div class="flex justify-end gap-2">
            <a href="{{ $editing ? route('super-admin.referrals.programs.show', $program) : route('super-admin.referrals.programs.index') }}" class="sa-btn-secondary">Cancel</a>
            <button type="submit" class="sa-btn-primary">{{ $editing ? 'Save program' : 'Create program' }}</button>
        </div>
    </form>
</x-super-admin.layout>
