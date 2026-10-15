<x-super-admin.guest-layout title="Set up two-factor authentication" description="Two-factor authentication is required for admin accounts. Add this account to an authenticator app (1Password, Google Authenticator, Authy…), then enter the code it shows.">
    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200">
        <p class="text-[11px] font-semibold tracking-wider text-zinc-500 uppercase">Scan with your authenticator app</p>
        <div class="mt-3 flex justify-center">
            <div class="rounded-lg bg-white p-3 ring-1 ring-zinc-200" role="img" aria-label="QR code for the authenticator app">{!! $qrCodeSvg !!}</div>
        </div>

        <p class="mt-4 text-[11px] font-semibold tracking-wider text-zinc-500 uppercase">Setup key</p>
        <p class="mt-1.5 font-mono text-sm break-all text-zinc-900 select-all">{{ $secret }}</p>
        <a href="{{ $otpauthUrl }}" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">Open in authenticator app<x-super-admin.icon name="chevron-right" class="size-3.5" /></a>
    </div>

    <form method="POST" action="{{ route('super-admin.two-factor.confirm') }}" class="mt-5 space-y-4">
        @csrf

        <div>
            <label for="code" class="sa-label">6-digit code</label>
            <input id="code" name="code" type="text" inputmode="numeric" required autofocus autocomplete="one-time-code" class="sa-input py-2.5 text-center font-mono text-lg tracking-[0.3em]">
            @error('code')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="sa-btn-primary w-full py-2.5">Confirm and sign in</button>
    </form>
</x-super-admin.guest-layout>
