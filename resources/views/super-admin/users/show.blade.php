<x-super-admin.layout :title="$user->name ?: $user->email" :description="$user->email" :back="route('super-admin.users.index')" back-label="All users">
    <x-slot:actions>
        <x-super-admin.status-badge :status="$user->email_verified_at ? 'verified' : 'unverified'" />
        <x-super-admin.status-badge :status="$user->hasTwoFactorEnabled() ? 'enabled' : 'off'" />
    </x-slot:actions>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-super-admin.card title="Account">
            <div class="flex items-center gap-4 border-b border-zinc-100 px-5 py-5">
                <x-super-admin.avatar :name="$user->name ?: $user->email" size="size-12" class="text-base" />
                <div class="min-w-0">
                    <p class="truncate font-semibold text-zinc-900">{{ $user->name }}</p>
                    <p class="truncate text-sm text-zinc-500">{{ $user->email }}</p>
                </div>
            </div>
            <dl class="sa-dl">
                <div><dt>ID</dt><dd class="font-mono text-xs break-all">{{ $user->id }}</dd></div>
                <div><dt>Joined</dt><dd>{{ $user->created_at?->toDayDateTimeString() }}</dd></div>
                <div><dt>Email verified</dt><dd>{{ $user->email_verified_at?->toDayDateTimeString() ?? 'Not verified' }}</dd></div>
                @if ($user->hasPendingEmailChange())
                    <div><dt>Pending email change</dt><dd>{{ $user->pending_email }}</dd></div>
                @endif
                <div><dt>Two-factor</dt><dd><x-super-admin.status-badge :status="$user->hasTwoFactorEnabled() ? 'enabled' : 'off'" /></dd></div>
                <div><dt>Onboarding</dt><dd>{{ $user->hasCompletedOnboarding() ? 'Completed '.$user->onboarding_completed_at->toFormattedDateString() : 'Not completed' }}</dd></div>
                <div><dt>Platform admin (API)</dt><dd>{{ $user->isPlatformAdmin() ? 'Yes' : 'No' }}</dd></div>
                @if ($user->referral)
                    <div><dt>Referred</dt><dd>Yes, {{ $user->referral->created_at?->toFormattedDateString() }}</dd></div>
                @endif
            </dl>
        </x-super-admin.card>

        <x-super-admin.card title="Workspaces" :description="$user->workspaces->count().' '.str('membership')->plural($user->workspaces->count())" class="lg:col-span-2">
            @if ($user->workspaces->isEmpty())
                <x-super-admin.empty icon="workspace">Not a member of any workspace.</x-super-admin.empty>
            @else
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Workspace</th>
                            <th>Role</th>
                            <th class="text-right">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($user->workspaces as $workspace)
                            <tr class="relative transition hover:bg-zinc-50/80">
                                <td>
                                    <a href="{{ route('super-admin.workspaces.show', $workspace) }}" class="font-medium text-zinc-900 after:absolute after:inset-0">{{ $workspace->name }}</a>
                                    @if ($workspace->trashed())
                                        <x-super-admin.status-badge status="deleted" class="ml-1" />
                                    @endif
                                </td>
                                <td><span class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-xs font-medium text-zinc-600 capitalize">{{ $workspace->pivot->role }}</span></td>
                                <td class="text-right text-zinc-500">{{ $workspace->pivot->joined_at ? \Illuminate\Support\Carbon::parse($workspace->pivot->joined_at)->toFormattedDateString() : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-super-admin.card>

        <x-super-admin.card title="Recent sign-in activity" class="lg:col-span-3">
            @if ($authEvents->isEmpty())
                <x-super-admin.empty icon="lock">No sign-in activity recorded.</x-super-admin.empty>
            @else
                <div class="overflow-x-auto">
                    <table class="sa-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Event</th>
                                <th>IP</th>
                                <th>Device</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($authEvents as $event)
                                <tr>
                                    <td class="whitespace-nowrap text-zinc-500" title="{{ $event->created_at }}">{{ $event->created_at?->diffForHumans() }}</td>
                                    <td class="text-zinc-900 capitalize">{{ str_replace('_', ' ', $event->event->value) }}</td>
                                    <td class="font-mono text-xs text-zinc-600">{{ $event->ip_address ?? '—' }}</td>
                                    <td class="max-w-md truncate text-xs text-zinc-500" title="{{ $event->user_agent }}">{{ $event->user_agent ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-super-admin.card>

        <x-super-admin.card title="SuperAdmin actions on this user" class="lg:col-span-3">
            @include('super-admin.audit-log.partials.entries', ['entries' => $superAdminActivity, 'empty' => 'No super admin has acted on this user.'])
        </x-super-admin.card>
    </div>
</x-super-admin.layout>
