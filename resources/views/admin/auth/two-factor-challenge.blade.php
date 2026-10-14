<x-admin.guest-layout title="Two-factor authentication">
    <p class="text-sm text-zinc-600">Enter the 6-digit code from your authenticator app, or one of your recovery codes.</p>

    <form method="POST" action="{{ route('panel.two-factor.verify') }}" class="mt-4 space-y-4">
        @csrf

        <div>
            <label for="code" class="block text-sm font-medium">Code</label>
            <input id="code" name="code" type="text" required autofocus autocomplete="one-time-code"
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 font-mono text-sm tracking-widest focus:border-zinc-900 focus:outline-none">
            @error('code')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-700">Verify</button>
    </form>

    <p class="mt-4 text-center text-sm"><a href="{{ route('panel.login') }}" class="text-zinc-500 hover:text-zinc-900">Start over</a></p>
</x-admin.guest-layout>
