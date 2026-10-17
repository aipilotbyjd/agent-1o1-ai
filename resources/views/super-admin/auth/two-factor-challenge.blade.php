<x-super-admin.guest-layout title="Two-factor authentication" description="Enter the 6-digit code from your authenticator app, or one of your recovery codes.">
    <form method="POST" action="{{ route('super-admin.two-factor.verify') }}" class="space-y-4">
        @csrf

        <x-super-admin.field for="code" label="Code">
            <input id="code" name="code" type="text" required autofocus autocomplete="one-time-code" class="sa-input py-2.5 text-center font-mono text-lg tracking-[0.3em]">
        </x-super-admin.field>

        <button type="submit" class="sa-btn-primary w-full py-2.5">Verify</button>
    </form>

    <p class="mt-5 text-center text-sm"><a href="{{ route('super-admin.login') }}" class="font-medium text-zinc-500 hover:text-zinc-900">Start over</a></p>
</x-super-admin.guest-layout>
