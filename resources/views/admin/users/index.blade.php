<x-admin.layout title="Users">
    <form method="GET" action="{{ route('panel.users.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-64 flex-1">
            <label for="q" class="block text-xs font-medium text-zinc-500">Search</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Email, name or ID"
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none">
        </div>
        <div>
            <label for="two_factor" class="block text-xs font-medium text-zinc-500">2FA</label>
            <select id="two_factor" name="two_factor" class="mt-1 block rounded-md border border-zinc-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option value="enabled" @selected(($filters['two_factor'] ?? '') === 'enabled')>Enabled</option>
                <option value="disabled" @selected(($filters['two_factor'] ?? '') === 'disabled')>Not enabled</option>
            </select>
        </div>
        <div>
            <label for="verified" class="block text-xs font-medium text-zinc-500">Email verified</label>
            <select id="verified" name="verified" class="mt-1 block rounded-md border border-zinc-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option value="yes" @selected(($filters['verified'] ?? '') === 'yes')>Yes</option>
                <option value="no" @selected(($filters['verified'] ?? '') === 'no')>No</option>
            </select>
        </div>
        <button type="submit" class="rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-700">Filter</button>
        @if (array_filter($filters))
            <a href="{{ route('panel.users.index') }}" class="py-2 text-sm text-zinc-500 hover:text-zinc-900">Clear</a>
        @endif
    </form>

    <x-admin.card class="mt-6">
        @if ($users->isEmpty())
            <p class="px-5 py-6 text-sm text-zinc-500">No users match.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-zinc-500 uppercase">
                        <tr>
                            <th class="px-5 py-2 font-medium">User</th>
                            <th class="px-5 py-2 font-medium">Workspaces</th>
                            <th class="px-5 py-2 font-medium">Verified</th>
                            <th class="px-5 py-2 font-medium">2FA</th>
                            <th class="px-5 py-2 font-medium">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($users as $user)
                            <tr class="hover:bg-zinc-50">
                                <td class="px-5 py-2">
                                    <a href="{{ route('panel.users.show', $user) }}" class="font-medium hover:underline">{{ $user->email }}</a>
                                    <p class="text-xs text-zinc-500">{{ $user->name }}</p>
                                </td>
                                <td class="px-5 py-2 tabular-nums">{{ $user->workspaces_count }}</td>
                                <td class="px-5 py-2"><x-admin.status-badge :status="$user->email_verified_at ? 'yes' : 'no'" /></td>
                                <td class="px-5 py-2"><x-admin.status-badge :status="$user->hasTwoFactorEnabled() ? 'enabled' : 'off'" /></td>
                                <td class="px-5 py-2 whitespace-nowrap text-zinc-500" title="{{ $user->created_at }}">{{ $user->created_at?->toFormattedDateString() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.card>

    <div class="mt-4">{{ $users->links() }}</div>
</x-admin.layout>
