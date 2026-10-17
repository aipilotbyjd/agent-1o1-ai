@php
    $editing = $rule->exists;
    $value = fn (string $field) => old($field, $rule->{$field});
    $enumValue = fn (string $field) => old($field, $rule->{$field} instanceof \BackedEnum ? $rule->{$field}->value : $rule->{$field});
    $date = fn (string $field) => old($field, $rule->{$field}?->format('Y-m-d\TH:i'));
    $condition = fn (string $key) => old('conditions.'.$key, data_get($rule->conditions ?? [], $key));
    $conditionList = fn (string $key) => (array) (old('conditions.'.$key, data_get($rule->conditions ?? [], $key)) ?? []);
@endphp

<x-super-admin.layout :title="$editing ? 'Edit rule' : 'New reward rule'" :description="$program->name" :back="route('super-admin.referrals.programs.show', $program)" :back-label="$program->name">
    <form method="POST" action="{{ $editing ? route('super-admin.referrals.rules.update', $rule) : route('super-admin.referrals.programs.rules.store', $program) }}" class="space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-super-admin.form-errors />

        <x-super-admin.card title="When and who">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                <x-super-admin.field for="name" label="Name" class="sm:col-span-2">
                    <input id="name" name="name" type="text" required maxlength="255" value="{{ $value('name') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.switch name="is_active" label="Active" :checked="(bool) old('is_active', $rule->is_active)" class="self-end" />
                <x-super-admin.field for="description" label="Description" class="sm:col-span-3">
                    <input id="description" name="description" type="text" maxlength="2000" value="{{ $value('description') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="trigger" label="Trigger">
                    <select id="trigger" name="trigger" required class="sa-input pr-8">
                        @foreach (\App\Enums\Referrals\ReferralTrigger::ruleValues() as $trigger)
                            <option value="{{ $trigger }}" @selected($enumValue('trigger') === $trigger)>{{ ucfirst(str_replace('_', ' ', $trigger)) }}</option>
                        @endforeach
                    </select>
                </x-super-admin.field>
                <x-super-admin.field for="recipient" label="Rewarded">
                    <select id="recipient" name="recipient" required class="sa-input pr-8">
                        @foreach (\App\Enums\Referrals\ReferralRecipient::cases() as $recipient)
                            <option value="{{ $recipient->value }}" @selected($enumValue('recipient') === $recipient->value)>{{ ucfirst($recipient->value) }}</option>
                        @endforeach
                    </select>
                </x-super-admin.field>
                <x-super-admin.field for="milestone_count" label="Milestone count" data-show-when="trigger:milestone">
                    <input id="milestone_count" name="milestone_count" type="number" min="1" value="{{ $value('milestone_count') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="rule_starts_at" label="Starts" hint="Leave blank to start now." error="starts_at">
                    <input id="rule_starts_at" name="starts_at" type="datetime-local" value="{{ $date('starts_at') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="rule_ends_at" label="Ends" hint="Leave blank to run indefinitely." error="ends_at">
                    <input id="rule_ends_at" name="ends_at" type="datetime-local" value="{{ $date('ends_at') }}" class="sa-input">
                </x-super-admin.field>
            </div>
        </x-super-admin.card>

        <x-super-admin.card title="Reward" description="Only the fields for the chosen type are shown.">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                <x-super-admin.field for="reward_type" label="Type">
                    <select id="reward_type" name="reward_type" required class="sa-input pr-8">
                        @foreach (\App\Enums\Referrals\ReferralRewardType::cases() as $type)
                            <option value="{{ $type->value }}" @selected($enumValue('reward_type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </x-super-admin.field>
                <x-super-admin.field for="credits_amount" label="Credits" data-show-when="reward_type:credits">
                    <input id="credits_amount" name="credits_amount" type="number" min="1" value="{{ $value('credits_amount') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="plan_id" label="Plan" data-show-when="reward_type:plan_time">
                    <select id="plan_id" name="plan_id" class="sa-input pr-8">
                        <option value="">—</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected(old('plan_id', $rule->plan_id) === $plan->id)>{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </x-super-admin.field>
                <x-super-admin.field for="duration_days" label="Plan days" data-show-when="reward_type:plan_time">
                    <input id="duration_days" name="duration_days" type="number" min="1" max="3650" value="{{ $value('duration_days') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="amount_cents" label="Invoice credit (cents)" data-show-when="reward_type:stripe_balance_credit">
                    <input id="amount_cents" name="amount_cents" type="number" min="1" value="{{ $value('amount_cents') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="amount_percent_of_plan" label="Or % of plan price" hint="Use instead of a fixed amount." data-show-when="reward_type:stripe_balance_credit">
                    <input id="amount_percent_of_plan" name="amount_percent_of_plan" type="number" min="1" max="1000" value="{{ $value('amount_percent_of_plan') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="trial_days" label="Trial extension days" data-show-when="reward_type:trial_extension">
                    <input id="trial_days" name="trial_days" type="number" min="1" max="365" value="{{ $value('trial_days') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="if_already_on_plan" label="If already on the plan" data-show-when="reward_type:plan_time">
                    <select id="if_already_on_plan" name="if_already_on_plan" class="sa-input pr-8">
                        @foreach (\App\Enums\Referrals\AlreadyOnPlanBehavior::cases() as $behavior)
                            <option value="{{ $behavior->value }}" @selected($enumValue('if_already_on_plan') === $behavior->value)>{{ ucfirst(str_replace('_', ' ', $behavior->value)) }}</option>
                        @endforeach
                    </select>
                </x-super-admin.field>
                <x-super-admin.field for="fallback_credits" label="Fallback credits" hint="Given instead when converting to credits." data-show-when="reward_type:plan_time">
                    <input id="fallback_credits" name="fallback_credits" type="number" min="0" value="{{ $value('fallback_credits') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="hold_days" label="Hold days" hint="Leave blank to use the program's default.">
                    <input id="hold_days" name="hold_days" type="number" min="0" max="365" value="{{ $value('hold_days') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="max_per_recipient" label="Max per recipient" hint="Leave blank for no cap.">
                    <input id="max_per_recipient" name="max_per_recipient" type="number" min="1" value="{{ $value('max_per_recipient') }}" class="sa-input">
                </x-super-admin.field>
            </div>
        </x-super-admin.card>

        <x-super-admin.card title="Conditions" description="Narrow which payments count. Leave blank to match every payment." data-show-when="trigger:{{ implode(',', [\App\Enums\Referrals\ReferralTrigger::FirstPayment->value, \App\Enums\Referrals\ReferralTrigger::RepeatPayment->value, \App\Enums\Referrals\ReferralTrigger::Milestone->value]) }}">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                <x-super-admin.field for="min_payment_cents" label="Minimum payment (cents)" error="conditions.min_payment_cents">
                    <input id="min_payment_cents" name="conditions[min_payment_cents]" type="number" min="0" value="{{ $condition('min_payment_cents') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="nth_payment_min" label="From payment #" error="conditions.nth_payment_min">
                    <input id="nth_payment_min" name="conditions[nth_payment_min]" type="number" min="1" value="{{ $condition('nth_payment_min') }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="nth_payment_max" label="Up to payment #" error="conditions.nth_payment_max">
                    <input id="nth_payment_max" name="conditions[nth_payment_max]" type="number" min="1" value="{{ $condition('nth_payment_max') }}" class="sa-input">
                </x-super-admin.field>
                @foreach ([
                    'plan_ids' => ['Plans', $plans->pluck('name', 'id')->all()],
                    'billing_intervals' => ['Billing intervals', collect(\App\Enums\Billing\BillingInterval::cases())->mapWithKeys(fn ($interval) => [$interval->value => ucfirst($interval->value)])->all()],
                    'payment_sources' => ['Payment sources', collect(\App\Enums\Referrals\ReferralPaymentSource::cases())->mapWithKeys(fn ($source) => [$source->value => ucfirst(str_replace('_', ' ', $source->value))])->all()],
                ] as $key => [$legend, $options])
                    <fieldset class="sm:col-span-3">
                        <legend class="sa-label">{{ $legend }}</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($options as $optionValue => $optionLabel)
                                <label class="sa-choice">
                                    <input type="checkbox" name="conditions[{{ $key }}][]" value="{{ $optionValue }}" @checked(in_array($optionValue, $conditionList($key), true))>
                                    {{ $optionLabel }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </x-super-admin.card>

        <x-super-admin.form-actions :cancel="route('super-admin.referrals.programs.show', $program)">
            <button type="submit" class="sa-btn-primary">{{ $editing ? 'Save rule' : 'Add rule' }}</button>
        </x-super-admin.form-actions>
    </form>
</x-super-admin.layout>
