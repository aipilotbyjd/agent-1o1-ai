<x-super-admin.layout title="Team">
    <x-super-admin.card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs text-zinc-500 uppercase">
                    <tr>
                        <th class="px-5 py-2 font-medium">Super admin</th>
                        <th class="px-5 py-2 font-medium">Two-factor</th>
                        <th class="px-5 py-2 font-medium">Status</th>
                        <th class="px-5 py-2 font-medium">Last sign-in</th>
                        <th class="px-5 py-2 font-medium">Last IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($team as $member)
                        <tr>
                            <td class="px-5 py-2">
                                <a href="{{ route('super-admin.audit-log.index', ['super_admin' => $member->id]) }}" class="font-medium hover:underline">{{ $member->email }}</a>
                                <div class="text-xs text-zinc-500">{{ $member->name }}</div>
                            </td>
                            <td class="px-5 py-2"><x-super-admin.status-badge :status="$member->hasTwoFactorEnabled() ? 'enabled' : 'off'" /></td>
                            <td class="px-5 py-2"><x-super-admin.status-badge :status="$member->isDisabled() ? 'disabled' : 'active'" /></td>
                            <td class="px-5 py-2 text-zinc-500" title="{{ $member->last_login_at }}">{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="px-5 py-2 font-mono text-xs">{{ $member->last_login_ip ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-super-admin.card>
    <p class="mt-3 text-xs text-zinc-500">Accounts are managed from the server with <code>php artisan super-admin:create|disable|enable|reset-password|reset-two-factor</code>.</p>
</x-super-admin.layout>
