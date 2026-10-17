<x-super-admin.guest-layout title="Sign in" description="Use your super admin credentials. You'll confirm with your authenticator next.">
    <form method="POST" action="{{ route('super-admin.login.store') }}" class="space-y-4">
        @csrf

        <x-super-admin.field for="email" label="Email">
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="sa-input">
        </x-super-admin.field>

        <x-super-admin.field for="password" label="Password">
            <input id="password" name="password" type="password" required autocomplete="current-password" class="sa-input">
        </x-super-admin.field>

        <button type="submit" class="sa-btn-primary w-full py-2.5">Continue</button>
    </form>
</x-super-admin.guest-layout>
