<x-super-admin.layout title="Users" :description="number_format($users->total()).' '.str('account')->plural($users->total()).' on the platform'">
    <x-super-admin.filters :action="route('super-admin.users.index')" :active="(bool) array_filter($filters)">
        <x-super-admin.search :value="$filters['q'] ?? ''" placeholder="Email, name or ID" />
        <div>
            <label for="two_factor" class="sa-label">2FA</label>
            <select id="two_factor" name="two_factor" class="sa-input pr-8">
                <option value="">Any</option>
                <option value="enabled" @selected(($filters['two_factor'] ?? '') === 'enabled')>Enabled</option>
                <option value="disabled" @selected(($filters['two_factor'] ?? '') === 'disabled')>Not enabled</option>
            </select>
        </div>
        <div>
            <label for="verified" class="sa-label">Email verified</label>
            <select id="verified" name="verified" class="sa-input pr-8">
                <option value="">Any</option>
                <option value="yes" @selected(($filters['verified'] ?? '') === 'yes')>Yes</option>
                <option value="no" @selected(($filters['verified'] ?? '') === 'no')>No</option>
            </select>
        </div>
    </x-super-admin.filters>

    <x-super-admin.card class="mt-6">
        @if ($users->isEmpty())
            <x-super-admin.empty icon="user">No users match.</x-super-admin.empty>
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
                            <tr class="relative transition hover:bg-zinc-50/80">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-super-admin.avatar :name="$user->name ?: $user->email" />
                                        <div class="min-w-0">
                                            <a href="{{ route('super-admin.users.show', $user) }}" class="font-medium text-zinc-900 after:absolute after:inset-0">{{ $user->name }}</a>
                                            <p class="truncate text-xs text-zinc-500">{{ $user->email }}</p>
                                        </div>
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

    <div class="mt-4">{{ $users->links() }}</div>
</x-super-admin.layout>
