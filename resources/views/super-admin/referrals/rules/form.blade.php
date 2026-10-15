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
                <div class="sm:col-span-2">
                    <label for="name" class="sa-label">Name</label>
                    <input id="name" name="name" type="text" required maxlength="255" value="{{ $value('name') }}" class="sa-input">
                </div>
                <label class="flex items-end gap-2 pb-2 text-sm text-zinc-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="size-4 rounded border-zinc-300 accent-zinc-900" @checked((bool) old('is_active', $rule->is_active))>
                    Active
                </label>
                <div class="sm:col-span-3">
                    <label for="description" class="sa-label">Description</label>
                    <input id="description" name="description" type="text" maxlength="2000" value="{{ $value('description') }}" class="sa-input">
                </div>
                <div>
                    <label for="trigger" class="sa-label">Trigger</label>
                    <select id="trigger" name="trigger" required class="sa-input pr-8">
                        @foreach (\App\Enums\Referrals\ReferralTrigger::ruleValues() as $trigger)
                            <option value="{{ $trigger }}" @selected($enumValue('trigger') === $trigger)>{{ str_replace('_', ' ', $trigger) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="recipient" class="sa-label">Rewarded</label>
                    <select id="recipient" name="recipient" required class="sa-input pr-8">
                        @foreach (\App\Enums\Referrals\ReferralRecipient::cases() as $recipient)
                            <option value="{{ $recipient->value }}" @selected($enumValue('recipient') === $recipient->value)>{{ $recipient->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="milestone_count" class="sa-label">Milestone count (milestone triggers)</label>
                    <input id="milestone_count" name="milestone_count" type="number" min="1" value="{{ $value('milestone_count') }}" class="sa-input">
                </div>
            </div>
        </x-super-admin.card>

        <x-super-admin.card title="Reward" description="Fill in the fields for the chosen type; the others are ignored.">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                <div>
                    <label for="reward_type" class="sa-label">Type</label>
                    <select id="reward_type" name="reward_type" required class="sa-input pr-8">
                        @foreach (\App\Enums\Referrals\ReferralRewardType::cases() as $type)
                            <option value="{{ $type->value }}" @selected($enumValue('reward_type') === $type->value)>{{ str_replace('_', ' ', $type->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="credits_amount" class="sa-label">Credits</label>
                    <input id="credits_amount" name="credits_amount" type="number" min="1" value="{{ $value('credits_amount') }}" class="sa-input">
                </div>
                <div>
                    <label for="plan_id" class="sa-label">Plan (plan time)</label>
                    <select id="plan_id" name="plan_id" class="sa-input pr-8">
                        <option value="">—</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected(old('plan_id', $rule->plan_id) === $plan->id)>{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="duration_days" class="sa-label">Plan days</label>
                    <input id="duration_days" name="duration_days" type="number" min="1" max="3650" value="{{ $value('duration_days') }}" class="sa-input">
                </div>
                <div>
                    <label for="amount_cents" class="sa-label">Invoice credit (cents)</label>
                    <input id="amount_cents" name="amount_cents" type="number" min="1" value="{{ $value('amount_cents') }}" class="sa-input">
                </div>
                <div>
                    <label for="amount_percent_of_plan" class="sa-label">…or % of plan price</label>
                    <input id="amount_percent_of_plan" name="amount_percent_of_plan" type="number" min="1" max="1000" value="{{ $value('amount_percent_of_plan') }}" class="sa-input">
                </div>
                <div>
                    <label for="trial_days" class="sa-label">Trial extension days</label>
                    <input id="trial_days" name="trial_days" type="number" min="1" max="365" value="{{ $value('trial_days') }}" class="sa-input">
                </div>
                <div>
                    <label for="if_already_on_plan" class="sa-label">If already on the plan</label>
                    <select id="if_already_on_plan" name="if_already_on_plan" class="sa-input pr-8">
                        @foreach (\App\Enums\Referrals\AlreadyOnPlanBehavior::cases() as $behavior)
                            <option value="{{ $behavior->value }}" @selected($enumValue('if_already_on_plan') === $behavior->value)>{{ str_replace('_', ' ', $behavior->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="fallback_credits" class="sa-label">Fallback credits</label>
                    <input id="fallback_credits" name="fallback_credits" type="number" min="0" value="{{ $value('fallback_credits') }}" class="sa-input">
                </div>
                <div>
                    <label for="hold_days" class="sa-label">Hold days (blank = program default)</label>
                    <input id="hold_days" name="hold_days" type="number" min="0" max="365" value="{{ $value('hold_days') }}" class="sa-input">
                </div>
                <div>
                    <label for="max_per_recipient" class="sa-label">Max per recipient (blank = no cap)</label>
                    <input id="max_per_recipient" name="max_per_recipient" type="number" min="1" value="{{ $value('max_per_recipient') }}" class="sa-input">
                </div>
            </div>
        </x-super-admin.card>

        <x-super-admin.card title="Conditions" description="Payment triggers only. Leave blank to match every payment.">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                <div>
                    <label for="min_payment_cents" class="sa-label">Minimum payment (cents)</label>
                    <input id="min_payment_cents" name="conditions[min_payment_cents]" type="number" min="0" value="{{ $condition('min_payment_cents') }}" class="sa-input">
                </div>
                <div>
                    <label for="nth_payment_min" class="sa-label">From payment #</label>
                    <input id="nth_payment_min" name="conditions[nth_payment_min]" type="number" min="1" value="{{ $condition('nth_payment_min') }}" class="sa-input">
                </div>
                <div>
                    <label for="nth_payment_max" class="sa-label">Up to payment #</label>
                    <input id="nth_payment_max" name="conditions[nth_payment_max]" type="number" min="1" value="{{ $condition('nth_payment_max') }}" class="sa-input">
                </div>
                @foreach ([
                    'plan_ids' => ['Plans', $plans->pluck('name', 'id')->all()],
                    'billing_intervals' => ['Billing intervals', collect(\App\Enums\Billing\BillingInterval::cases())->mapWithKeys(fn ($interval) => [$interval->value => $interval->value])->all()],
                    'payment_sources' => ['Payment sources', collect(\App\Enums\Referrals\ReferralPaymentSource::cases())->mapWithKeys(fn ($source) => [$source->value => str_replace('_', ' ', $source->value)])->all()],
                ] as $key => [$legend, $options])
                    <fieldset>
                        <legend class="sa-label">{{ $legend }}</legend>
                        <div class="mt-2 space-y-1.5">
                            @foreach ($options as $optionValue => $optionLabel)
                                <label class="flex items-center gap-2 text-sm text-zinc-700">
                                    <input type="checkbox" name="conditions[{{ $key }}][]" value="{{ $optionValue }}" class="size-4 rounded border-zinc-300 accent-zinc-900" @checked(in_array($optionValue, $conditionList($key), true))>
                                    {{ $optionLabel }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
                <div>
                    <label for="rule_starts_at" class="sa-label">Starts</label>
                    <input id="rule_starts_at" name="starts_at" type="datetime-local" value="{{ $date('starts_at') }}" class="sa-input">
                </div>
                <div>
                    <label for="rule_ends_at" class="sa-label">Ends</label>
                    <input id="rule_ends_at" name="ends_at" type="datetime-local" value="{{ $date('ends_at') }}" class="sa-input">
                </div>
            </div>
        </x-super-admin.card>

        <div class="flex justify-end gap-2">
            <a href="{{ route('super-admin.referrals.programs.show', $program) }}" class="sa-btn-secondary">Cancel</a>
            <button type="submit" class="sa-btn-primary">{{ $editing ? 'Save rule' : 'Add rule' }}</button>
        </div>
    </form>
</x-super-admin.layout>
