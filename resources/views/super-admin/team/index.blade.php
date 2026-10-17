<x-super-admin.layout title="Team" description="Everyone with a super admin account.">
    <x-super-admin.card>
        <div class="overflow-x-auto">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th>Super admin</th>
                        <th>Two-factor</th>
                        <th>Status</th>
                        <th>Last sign-in</th>
                        <th>Last IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($team as $member)
                        <tr>
                            <td>
                                <a href="{{ route('super-admin.audit-log.index', ['super_admin' => $member->id]) }}" class="sa-link">{{ $member->email }}</a>
                                <div class="text-xs text-zinc-500">{{ $member->name }}</div>
                            </td>
                            <td><x-super-admin.status-badge :status="$member->hasTwoFactorEnabled() ? 'enabled' : 'off'" /></td>
                            <td><x-super-admin.status-badge :status="$member->isDisabled() ? 'disabled' : 'active'" /></td>
                            <td class="text-zinc-500" title="{{ $member->last_login_at }}">{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="font-mono text-xs">{{ $member->last_login_ip ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-super-admin.card>
    <p class="mt-3 text-xs text-zinc-500">Accounts are managed from the server with <code>php artisan super-admin:create|disable|enable|reset-password|reset-two-factor</code>.</p>
</x-super-admin.layout>
