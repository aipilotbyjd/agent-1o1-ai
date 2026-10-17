<x-super-admin.layout title="Users" :description="number_format($users->total()).' '.str('account')->plural($users->total()).' on the platform'">
    <x-super-admin.filters :action="route('super-admin.users.index')" :active="(bool) array_filter($filters)">
        <x-super-admin.search :value="$filters['q'] ?? ''" placeholder="Search by email, name or ID" />
        <x-super-admin.filter-select name="two_factor" label="2FA" :value="$filters['two_factor'] ?? null" :options="['enabled' => 'Enabled', 'disabled' => 'Not enabled']" />
        <x-super-admin.filter-select name="suspended" label="Suspended" :value="$filters['suspended'] ?? null" :options="['yes' => 'Suspended', 'no' => 'Not suspended']" />
        <x-super-admin.filter-select name="verified" label="Email verified" :value="$filters['verified'] ?? null" :options="['yes' => 'Yes', 'no' => 'No']" />
    </x-super-admin.filters>

    <x-super-admin.card class="mt-6">
        @if ($users->isEmpty())
            <x-super-admin.empty :clear="array_filter($filters) ? route('super-admin.users.index') : null" icon="user">No users match.</x-super-admin.empty>
        @else
            <div class="overflow-x-auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th class="text-right">Workspaces</th>
                            <th>Verified</th>
                            <th>2FA</th>
                            <th class="text-right">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-super-admin.avatar :name="$user->name ?: $user->email" />
                                        <div class="min-w-0">
                                            <a href="{{ route('super-admin.users.show', $user) }}" class="sa-row-link">{{ $user->name }}</a>
                                            <p class="truncate text-xs text-zinc-500">{{ $user->email }}</p>
                                        </div>
                                        @if ($user->isSuspended())
                                            <x-super-admin.status-badge status="suspended" />
                                        @endif
                                    </div>
                                </td>
                                <td class="text-right text-zinc-700 tabular-nums">{{ $user->workspaces_count }}</td>
                                <td><x-super-admin.status-badge :status="$user->email_verified_at ? 'yes' : 'no'" /></td>
                                <td><x-super-admin.status-badge :status="$user->hasTwoFactorEnabled() ? 'enabled' : 'off'" /></td>
                                <td class="text-right whitespace-nowrap text-zinc-500" title="{{ $user->created_at }}">{{ $user->created_at?->toFormattedDateString() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-super-admin.card>

    <x-super-admin.pagination :paginator="$users" />
</x-super-admin.layout>
