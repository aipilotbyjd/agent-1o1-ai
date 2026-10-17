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
                <x-super-admin.field for="name" label="Name">
                    <input id="name" name="name" type="text" required maxlength="255" value="{{ $value('name') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="slug" label="Slug" :hint="$editing ? null : 'Leave blank to generate it from the name.'">
                    <input id="slug" name="slug" type="text" maxlength="255" value="{{ $value('slug') }}" class="sa-input font-mono">
                </x-super-admin.field>
                <x-super-admin.field for="description" label="Description" class="sm:col-span-2">
                    <textarea id="description" name="description" rows="2" maxlength="2000" class="sa-input">{{ $value('description') }}</textarea>
                </x-super-admin.field>
                <x-super-admin.field for="starts_at" label="Starts">
                    <input id="starts_at" name="starts_at" type="datetime-local" value="{{ $date('starts_at') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="ends_at" label="Ends">
                    <input id="ends_at" name="ends_at" type="datetime-local" value="{{ $date('ends_at') }}" class="sa-input">
                </x-super-admin.field>
            </div>
            <div class="grid grid-cols-1 gap-1 border-t border-zinc-100 px-2 py-2 sm:grid-cols-3">
                <x-super-admin.switch name="is_active" label="Active" description="Inactive programs stop attributing signups." :checked="$checked('is_active')" />
                <x-super-admin.switch name="require_verified_email" label="Require a verified email" description="Referees must verify before counting." :checked="$checked('require_verified_email')" />
                <x-super-admin.switch name="revoke_on_partial_refund" label="Revoke on partial refund" description="Not only on a full refund." :checked="$checked('revoke_on_partial_refund')" />
            </div>
        </x-super-admin.card>

        <x-super-admin.card title="Attribution and activation">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                <x-super-admin.field for="attribution_window_days" label="Attribution window (days)">
                    <input id="attribution_window_days" name="attribution_window_days" type="number" min="1" max="365" value="{{ $value('attribution_window_days') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="claim_window_hours" label="Claim window (hours)">
                    <input id="claim_window_hours" name="claim_window_hours" type="number" min="1" max="720" value="{{ $value('claim_window_hours') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="activation_event" label="Activation event">
                    <select id="activation_event" name="activation_event" class="sa-input pr-8">
                        @foreach (\App\Enums\Referrals\ReferralActivationEvent::cases() as $event)
                            <option value="{{ $event->value }}" @selected($enumValue('activation_event') === $event->value)>{{ ucfirst(str_replace('_', ' ', $event->value)) }}</option>
                        @endforeach
                    </select>
                </x-super-admin.field>
                <x-super-admin.field for="activation_min_count" label="Activation count">
                    <input id="activation_min_count" name="activation_min_count" type="number" min="1" max="1000" value="{{ $value('activation_min_count') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="activation_window_days" label="Activation window (days)">
                    <input id="activation_window_days" name="activation_window_days" type="number" min="1" max="365" value="{{ $value('activation_window_days') }}" class="sa-input">
                </x-super-admin.field>
            </div>
        </x-super-admin.card>

        <x-super-admin.card title="Rewards and limits">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                <x-super-admin.field for="approval_mode" label="Approval">
                    <select id="approval_mode" name="approval_mode" class="sa-input pr-8">
                        @foreach (\App\Enums\Referrals\ReferralApprovalMode::cases() as $mode)
                            <option value="{{ $mode->value }}" @selected($enumValue('approval_mode') === $mode->value)>{{ ucfirst($mode->value) }}</option>
                        @endforeach
                    </select>
                </x-super-admin.field>
                <x-super-admin.field for="default_hold_days" label="Default hold (days)">
                    <input id="default_hold_days" name="default_hold_days" type="number" min="0" max="365" value="{{ $value('default_hold_days') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="referrer_min_account_age_days" label="Referrer min account age (days)">
                    <input id="referrer_min_account_age_days" name="referrer_min_account_age_days" type="number" min="0" max="3650" value="{{ $value('referrer_min_account_age_days') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="referrer_monthly_credit_cap" label="Referrer credits / month" hint="Leave blank for no cap.">
                    <input id="referrer_monthly_credit_cap" name="referrer_monthly_credit_cap" type="number" min="0" value="{{ $value('referrer_monthly_credit_cap') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="referrer_max_stacked_plan_days" label="Max stacked plan days" hint="Leave blank for no cap.">
                    <input id="referrer_max_stacked_plan_days" name="referrer_max_stacked_plan_days" type="number" min="0" max="36500" value="{{ $value('referrer_max_stacked_plan_days') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="referrer_max_referrals_per_month" label="Max referrals / month" hint="Leave blank for no cap.">
                    <input id="referrer_max_referrals_per_month" name="referrer_max_referrals_per_month" type="number" min="1" max="100000" value="{{ $value('referrer_max_referrals_per_month') }}" class="sa-input">
                </x-super-admin.field>
                <fieldset class="sm:col-span-3">
                    <legend class="sa-label">Referrers must be on</legend>
                    <input type="hidden" name="referrer_eligible_plan_ids" value="">
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($plans as $plan)
                            <label class="sa-choice">
                                <input type="checkbox" name="referrer_eligible_plan_ids[]" value="{{ $plan->id }}" @checked(in_array($plan->id, $eligiblePlans ?? [], true))>
                                {{ $plan->name }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1.5 text-xs text-zinc-500">Pick none to allow referrers on any plan.</p>
                </fieldset>
            </div>
        </x-super-admin.card>

        <x-super-admin.card title="Fraud checks" description="Referrals that fail a check are rejected before any reward is earned.">
            <div class="grid grid-cols-1 gap-1 px-2 py-2 sm:grid-cols-2">
                @foreach ([
                    'shared_workspace' => ['Shared workspace', 'Referrer and referee belong to the same workspace.'],
                    'same_email_domain' => ['Same email domain', 'Both sign up with the same company domain.'],
                    'disposable_email' => ['Disposable email', 'The referee uses a throwaway address.'],
                    'card_fingerprint' => ['Reused payment card', 'The card has already paid on another account.'],
                ] as $key => [$label, $description])
                    <x-super-admin.switch :name="'fraud_checks['.$key.']'" :label="$label" :description="$description" :checked="(bool) $fraud($key)" />
                @endforeach
            </div>
            <div class="grid grid-cols-1 gap-4 border-t border-zinc-100 px-5 py-4 sm:grid-cols-2">
                <div class="group/panel rounded-xl ring-1 ring-zinc-200/80">
                    <x-super-admin.switch name="fraud_checks[ip_velocity][enabled]" label="Limit signups per IP" description="Reject referrals once an IP passes the limit." :checked="(bool) $fraud('ip_velocity.enabled')" />
                    <div class="grid grid-cols-2 gap-3 border-t border-zinc-100 px-3 py-3 transition-opacity group-has-[.sa-switch:not(:checked)]/panel:opacity-50">
                        <x-super-admin.field for="ip_velocity_max" label="Max signups" error="fraud_checks.ip_velocity.max">
                            <input id="ip_velocity_max" name="fraud_checks[ip_velocity][max]" type="number" min="1" max="10000" value="{{ $fraud('ip_velocity.max') }}" class="sa-input">
                        </x-super-admin.field>
                        <x-super-admin.field for="ip_velocity_hours" label="Per hours" error="fraud_checks.ip_velocity.hours">
                            <input id="ip_velocity_hours" name="fraud_checks[ip_velocity][hours]" type="number" min="1" max="720" value="{{ $fraud('ip_velocity.hours') }}" class="sa-input">
                        </x-super-admin.field>
                    </div>
                </div>

                <div class="group/panel rounded-xl ring-1 ring-zinc-200/80">
                    <x-super-admin.switch name="fraud_checks[velocity_alert][enabled]" label="Alert on a signup spike" description="Notify the team; referrals still go through." :checked="(bool) $fraud('velocity_alert.enabled')" />
                    <x-super-admin.field for="velocity_alert_max" label="Signups per hour" error="fraud_checks.velocity_alert.max_per_hour" class="border-t border-zinc-100 px-3 py-3 transition-opacity group-has-[.sa-switch:not(:checked)]/panel:opacity-50">
                        <input id="velocity_alert_max" name="fraud_checks[velocity_alert][max_per_hour]" type="number" min="1" max="100000" value="{{ $fraud('velocity_alert.max_per_hour') }}" class="sa-input">
                    </x-super-admin.field>
                </div>
            </div>
        </x-super-admin.card>

        <x-super-admin.form-actions :cancel="$editing ? route('super-admin.referrals.programs.show', $program) : route('super-admin.referrals.programs.index')">
            <button type="submit" class="sa-btn-primary">{{ $editing ? 'Save program' : 'Create program' }}</button>
        </x-super-admin.form-actions>
    </form>
</x-super-admin.layout>
