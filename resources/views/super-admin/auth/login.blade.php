<x-super-admin.guest-layout title="Sign in" description="Use your super admin credentials. You'll confirm with your authenticator next.">
    <form method="POST" action="{{ route('super-admin.login.store') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="sa-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="sa-input">
            @error('email')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="sa-label">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="sa-input">
            @error('password')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="sa-btn-primary w-full py-2.5">Continue</button>
    </form>
</x-super-admin.guest-layout>
