<x-admin.layout title="Account">
    <div class="grid max-w-4xl grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-zinc-200 bg-white p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold">Details</h2>
            <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-zinc-500">Name</dt>
                    <dd>{{ $admin->name }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500">Email</dt>
                    <dd>{{ $admin->email }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500">Last sign-in</dt>
                    <dd>{{ $admin->last_login_at?->toDayDateTimeString() ?? '—' }} <span class="text-zinc-500">{{ $admin->last_login_ip }}</span></dd>
                </div>
            </dl>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5">
            <h2 class="text-sm font-semibold">Change password</h2>
            <p class="mt-1 text-sm text-zinc-500">Every other session is signed out.</p>

            <form method="POST" action="{{ route('panel.account.password') }}" class="mt-4 space-y-3">
                @csrf
                @method('PUT')

                @foreach (['current_password' => 'Current password', 'password' => 'New password', 'password_confirmation' => 'Confirm new password'] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="block text-sm font-medium">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="password" required autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}"
                            class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none">
                        @error($field)
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <button type="submit" class="rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-700">Update password</button>
            </form>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5">
            <h2 class="text-sm font-semibold">Recovery codes</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ $remainingRecoveryCodes }} unused {{ str('code')->plural($remainingRecoveryCodes) }} left. Generating new codes invalidates the old ones.</p>

            <form method="POST" action="{{ route('panel.account.recovery-codes') }}" class="mt-4 space-y-3">
                @csrf

                <div>
                    <label for="recovery_current_password" class="block text-sm font-medium">Current password</label>
                    <input id="recovery_current_password" name="current_password" type="password" required autocomplete="current-password"
                        class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none">
                    @error('current_password', 'recoveryCodes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold hover:bg-zinc-100">Generate new codes</button>
            </form>
        </section>
    </div>
</x-admin.layout>
