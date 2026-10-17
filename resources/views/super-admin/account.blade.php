<x-super-admin.layout title="Account" description="Your super admin profile and sign-in security">
    <div class="grid max-w-5xl grid-cols-1 gap-6 lg:grid-cols-2">
        <x-super-admin.card class="lg:col-span-2">
            <div class="flex flex-wrap items-center gap-5 px-6 py-6">
                <x-super-admin.avatar :name="$superAdmin->name ?: $superAdmin->email" size="size-14" class="text-lg" />
                <div class="min-w-0 flex-1">
                    <p class="text-lg font-semibold text-zinc-900">{{ $superAdmin->name }}</p>
                    <p class="text-sm text-zinc-500">{{ $superAdmin->email }}</p>
                </div>
                <div class="text-sm sm:text-right">
                    <p class="text-zinc-500">Last sign-in</p>
                    <p class="font-medium text-zinc-900">{{ $superAdmin->last_login_at?->toDayDateTimeString() ?? '—' }}</p>
                    @if ($superAdmin->last_login_ip)
                        <p class="font-mono text-xs text-zinc-500">{{ $superAdmin->last_login_ip }}</p>
                    @endif
                </div>
            </div>
        </x-super-admin.card>

        <x-super-admin.card title="Change password" description="Every other session is signed out.">
            <form method="POST" action="{{ route('super-admin.account.password') }}" class="space-y-4 px-5 py-5">
                @csrf
                @method('PUT')

                @foreach (['current_password' => 'Current password', 'password' => 'New password', 'password_confirmation' => 'Confirm new password'] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="sa-label">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="password" required autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" class="sa-input">
                        @error($field)
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <button type="submit" class="sa-btn-primary">Update password</button>
            </form>
        </x-super-admin.card>

        <x-super-admin.card title="Recovery codes" :description="$remainingRecoveryCodes.' unused '.str('code')->plural($remainingRecoveryCodes).' left'">
            <div class="px-5 py-5">
                <div @class([
                    'flex items-center gap-3 rounded-xl px-4 py-3 text-sm ring-1',
                    'bg-amber-50 text-amber-900 ring-amber-600/20' => $remainingRecoveryCodes <= 2,
                    'bg-zinc-50 text-zinc-700 ring-zinc-200' => $remainingRecoveryCodes > 2,
                ])>
                    <x-super-admin.icon :name="$remainingRecoveryCodes <= 2 ? 'warning' : 'shield'" class="size-5" />
                    Generating new codes invalidates the old ones.
                </div>

                <form method="POST" action="{{ route('super-admin.account.recovery-codes') }}" class="mt-4 space-y-4">
                    @csrf

                    <div>
                        <label for="recovery_current_password" class="sa-label">Current password</label>
                        <input id="recovery_current_password" name="current_password" type="password" required autocomplete="current-password" class="sa-input">
                        @error('current_password', 'recoveryCodes')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="sa-btn-secondary">Generate new codes</button>
                </form>
            </div>
        </x-super-admin.card>
    </div>
</x-super-admin.layout>
