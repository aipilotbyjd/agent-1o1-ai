<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    <x-super-admin.card title="Plan grants" description="Plans held outside a subscription" class="lg:col-span-3">
        @if ($planGrants->isEmpty())
            <x-super-admin.empty icon="gift">No plan grants.</x-super-admin.empty>
        @else
            <div class="overflow-x-auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Granted</th>
                            <th>Expires</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($planGrants as $grant)
                            @php $isRevocable = ! $workspace->trashed() && $grant->source === \App\Enums\Billing\PlanGrantSource::Manual && $grant->status === \App\Enums\Billing\PlanGrantStatus::Active && ! $grant->expires_at?->isPast(); @endphp
                            <tr>
                                <td class="font-medium">{{ $grant->plan?->name ?? '—' }}</td>
                                <td class="capitalize">{{ str_replace('_', ' ', $grant->source->value) }}</td>
                                <td><x-super-admin.status-badge :status="$grant->status->value === 'active' && $grant->expires_at?->isPast() ? 'expired' : $grant->status" /></td>
                                <td class="whitespace-nowrap text-zinc-500">{{ $grant->granted_at?->toFormattedDateString() ?? '—' }}</td>
                                <td class="whitespace-nowrap text-zinc-500">{{ $grant->expires_at?->toFormattedDateString() ?? 'Never' }}</td>
                                <td class="text-right">
                                    @if ($isRevocable)
                                        <details class="inline-block text-left">
                                            <summary class="cursor-pointer text-xs font-semibold text-red-700 hover:text-red-900">Revoke</summary>
                                            <form method="POST" action="{{ route('super-admin.workspaces.plan-grants.revoke', [$workspace, $grant]) }}" class="mt-2 w-72 space-y-2" data-confirm="Revoke this {{ $grant->plan?->name }} grant? The workspace drops to whatever else it is entitled to.">
                                                @csrf
                                                <input name="reason" type="text" required minlength="5" maxlength="500" placeholder="Reason" class="sa-input">
                                                <input name="current_password" type="password" required autocomplete="current-password" placeholder="Your password" class="sa-input">
                                                <button type="submit" class="sa-btn-secondary w-full text-red-700">Revoke grant</button>
                                            </form>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($errors->revokePlanGrant->any())
                <p class="border-t border-zinc-100 px-5 py-2 text-xs text-red-600">{{ $errors->revokePlanGrant->first() }}</p>
            @endif
        @endif
    </x-super-admin.card>

    @unless ($workspace->trashed())
        <x-super-admin.card title="Add top-up credits" description="Non-expiring credits, spent after the plan allowance. Needs your password; logged with your reason." class="lg:col-span-1">
            <form method="POST" action="{{ route('super-admin.workspaces.credits.grant', $workspace) }}" class="space-y-3 px-5 py-4" data-confirm="Add top-up credits to {{ $workspace->name }}?">
                @csrf
                <div>
                    <label for="grant_credits" class="sa-label">Credits</label>
                    <input id="grant_credits" name="credits" type="number" min="1" max="{{ \App\Http\Requests\SuperAdmin\GrantWorkspaceCreditsRequest::MAX_CREDITS }}" required value="{{ old('credits') }}" class="sa-input tabular-nums">
                </div>
                <div>
                    <label for="grant_credits_reason" class="sa-label">Reason</label>
                    <input id="grant_credits_reason" name="reason" type="text" required minlength="5" maxlength="500" value="{{ old('reason') }}" placeholder="e.g. Refund for failed runs on 12 Oct" class="sa-input">
                </div>
                <div>
                    <label for="grant_credits_password" class="sa-label">Your password</label>
                    <input id="grant_credits_password" name="current_password" type="password" required autocomplete="current-password" class="sa-input">
                </div>
                @if ($errors->grantCredits->any())
                    <ul class="space-y-0.5 text-xs text-red-600">
                        @foreach ($errors->grantCredits->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                @endif
                <button type="submit" class="sa-btn-primary w-full">
                    <x-super-admin.icon name="coins" class="size-4" />
                    Add credits
                </button>
            </form>
        </x-super-admin.card>

        <x-super-admin.card title="Grant plan time" description="Gives a plan for a fixed number of days. Needs your password; logged with your reason." class="lg:col-span-2">
            <form method="POST" action="{{ route('super-admin.workspaces.plan-time.grant', $workspace) }}" class="grid grid-cols-1 gap-3 px-5 py-4 sm:grid-cols-2" data-confirm="Grant plan time to {{ $workspace->name }}?">
                @csrf
                <div>
                    <label for="grant_plan" class="sa-label">Plan</label>
                    <select id="grant_plan" name="plan_id" required class="sa-input pr-8">
                        @foreach ($grantablePlans as $grantablePlan)
                            <option value="{{ $grantablePlan->id }}" @selected(old('plan_id') === $grantablePlan->id)>{{ $grantablePlan->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="grant_days" class="sa-label">Days</label>
                    <input id="grant_days" name="days" type="number" min="1" max="{{ \App\Http\Requests\SuperAdmin\GrantWorkspacePlanTimeRequest::MAX_DAYS }}" required value="{{ old('days', 30) }}" class="sa-input tabular-nums">
                </div>
                <div class="sm:col-span-2">
                    <label for="grant_plan_reason" class="sa-label">Reason</label>
                    <input id="grant_plan_reason" name="reason" type="text" required minlength="5" maxlength="500" value="{{ old('reason') }}" placeholder="e.g. Partner trial agreed by email" class="sa-input">
                </div>
                <div>
                    <label for="grant_plan_password" class="sa-label">Your password</label>
                    <input id="grant_plan_password" name="current_password" type="password" required autocomplete="current-password" class="sa-input">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="sa-btn-primary w-full">
                        <x-super-admin.icon name="gift" class="size-4" />
                        Grant plan time
                    </button>
                </div>
                @if ($errors->grantPlanTime->any())
                    <ul class="space-y-0.5 text-xs text-red-600 sm:col-span-2">
                        @foreach ($errors->grantPlanTime->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                @endif
            </form>
        </x-super-admin.card>
    @endunless
</div>
