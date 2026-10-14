<x-super-admin.layout :title="$user->email">
    <a href="{{ route('super-admin.users.index') }}" class="text-sm text-zinc-500 hover:text-zinc-900">&larr; All users</a>

    <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-super-admin.card title="Account" class="lg:col-span-1">
            <dl class="space-y-3 px-5 py-4 text-sm">
                <div><dt class="text-zinc-500">Name</dt><dd>{{ $user->name }}</dd></div>
                <div><dt class="text-zinc-500">ID</dt><dd class="font-mono text-xs break-all">{{ $user->id }}</dd></div>
                <div><dt class="text-zinc-500">Joined</dt><dd>{{ $user->created_at?->toDayDateTimeString() }}</dd></div>
                <div>
                    <dt class="text-zinc-500">Email verified</dt>
                    <dd>{{ $user->email_verified_at?->toDayDateTimeString() ?? 'Not verified' }}</dd>
                </div>
                @if ($user->hasPendingEmailChange())
                    <div><dt class="text-zinc-500">Pending email change</dt><dd>{{ $user->pending_email }}</dd></div>
                @endif
                <div>
                    <dt class="text-zinc-500">Two-factor</dt>
                    <dd><x-super-admin.status-badge :status="$user->hasTwoFactorEnabled() ? 'enabled' : 'off'" /></dd>
                </div>
                <div>
                    <dt class="text-zinc-500">Onboarding</dt>
                    <dd>{{ $user->hasCompletedOnboarding() ? 'Completed '.$user->onboarding_completed_at->toFormattedDateString() : 'Not completed' }}</dd>
                </div>
                <div><dt class="text-zinc-500">Platform admin (API)</dt><dd>{{ $user->isPlatformAdmin() ? 'Yes' : 'No' }}</dd></div>
                @if ($user->referral)
                    <div><dt class="text-zinc-500">Referred</dt><dd>Yes, {{ $user->referral->created_at?->toFormattedDateString() }}</dd></div>
                @endif
            </dl>
        </x-super-admin.card>

        <x-super-admin.card title="Workspaces" class="lg:col-span-2">
            @if ($user->workspaces->isEmpty())
                <p class="px-5 py-6 text-sm text-zinc-500">Not a member of any workspace.</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-zinc-500 uppercase">
                        <tr>
                            <th class="px-5 py-2 font-medium">Workspace</th>
                            <th class="px-5 py-2 font-medium">Role</th>
                            <th class="px-5 py-2 font-medium">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($user->workspaces as $workspace)
                            <tr>
                                <td class="px-5 py-2">
                                    <a href="{{ route('super-admin.workspaces.show', $workspace) }}" class="font-medium hover:underline">{{ $workspace->name }}</a>
                                    @if ($workspace->trashed())
                                        <x-super-admin.status-badge status="deleted" class="ml-1" />
                                    @endif
                                </td>
                                <td class="px-5 py-2">{{ $workspace->pivot->role }}</td>
                                <td class="px-5 py-2 text-zinc-500">{{ $workspace->pivot->joined_at ? \Illuminate\Support\Carbon::parse($workspace->pivot->joined_at)->toFormattedDateString() : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-super-admin.card>

        <x-super-admin.card title="Recent sign-in activity" class="lg:col-span-3">
            @if ($authEvents->isEmpty())
                <p class="px-5 py-6 text-sm text-zinc-500">No sign-in activity recorded.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs text-zinc-500 uppercase">
                            <tr>
                                <th class="px-5 py-2 font-medium">When</th>
                                <th class="px-5 py-2 font-medium">Event</th>
                                <th class="px-5 py-2 font-medium">IP</th>
                                <th class="px-5 py-2 font-medium">Device</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($authEvents as $event)
                                <tr>
                                    <td class="px-5 py-2 whitespace-nowrap text-zinc-500" title="{{ $event->created_at }}">{{ $event->created_at?->diffForHumans() }}</td>
                                    <td class="px-5 py-2">{{ str_replace('_', ' ', $event->event->value) }}</td>
                                    <td class="px-5 py-2 font-mono text-xs">{{ $event->ip_address ?? '—' }}</td>
                                    <td class="max-w-md truncate px-5 py-2 text-xs text-zinc-500" title="{{ $event->user_agent }}">{{ $event->user_agent ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-super-admin.card>

        <x-super-admin.card title="Support actions" class="lg:col-span-3">
            <form method="POST" class="flex flex-wrap items-end gap-3 px-5 py-4"
                  onsubmit="return confirm('Run this action on {{ e($user->email) }}?')">
                @csrf
                <div class="min-w-56">
                    <label for="current_password" class="block text-xs font-medium text-zinc-500">Your password (to confirm)</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                        class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none">
                    @error('current_password', 'support')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" formaction="{{ route('super-admin.users.sign-out-everywhere', $user) }}" class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium hover:bg-zinc-100">Sign out of all API sessions</button>
                @if ($user->hasTwoFactorEnabled())
                    <button type="submit" formaction="{{ route('super-admin.users.reset-two-factor', $user) }}" class="rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Reset two-factor</button>
                @endif
                @unless ($user->hasVerifiedEmail())
                    <button type="submit" formaction="{{ route('super-admin.users.resend-verification', $user) }}" class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium hover:bg-zinc-100">Resend verification email</button>
                @endunless
            </form>
        </x-super-admin.card>

        <x-super-admin.card title="SuperAdmin actions on this user" class="lg:col-span-3">
            @include('super-admin.audit-log.partials.entries', ['entries' => $superAdminActivity, 'empty' => 'No super admin has acted on this user.'])
        </x-super-admin.card>
    </div>
</x-super-admin.layout>
