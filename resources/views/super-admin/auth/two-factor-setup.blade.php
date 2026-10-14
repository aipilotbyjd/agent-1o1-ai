<x-super-admin.guest-layout title="Set up two-factor authentication">
    <p class="text-sm text-zinc-600">Two-factor authentication is required for admin accounts. Add this account to an authenticator app (1Password, Google Authenticator, Authy…), then enter the code it shows.</p>

    <div class="mt-4 rounded-md border border-zinc-200 bg-zinc-50 p-3">
        <p class="text-xs font-medium tracking-wide text-zinc-500 uppercase">Setup key</p>
        <p class="mt-1 font-mono text-sm break-all select-all">{{ $secret }}</p>
        <a href="{{ $otpauthUrl }}" class="mt-2 inline-block text-sm font-medium text-zinc-900 underline">Open in authenticator app</a>
    </div>

    <form method="POST" action="{{ route('super-admin.two-factor.confirm') }}" class="mt-4 space-y-4">
        @csrf

        <div>
            <label for="code" class="block text-sm font-medium">6-digit code</label>
            <input id="code" name="code" type="text" inputmode="numeric" required autofocus autocomplete="one-time-code"
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 font-mono text-sm tracking-widest focus:border-zinc-900 focus:outline-none">
            @error('code')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-700">Confirm and sign in</button>
    </form>
</x-super-admin.guest-layout>
