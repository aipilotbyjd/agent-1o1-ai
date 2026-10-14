<x-super-admin.layout title="Recovery codes">
    <div class="max-w-xl rounded-xl border border-amber-200 bg-amber-50 p-5">
        <p class="text-sm text-amber-900">Store these somewhere safe, such as a password manager. Each code works once if you lose your authenticator. <strong>They won't be shown again.</strong></p>

        <ul class="mt-4 grid grid-cols-1 gap-2 font-mono text-sm sm:grid-cols-2">
            @foreach ($recoveryCodes as $code)
                <li class="rounded-md border border-amber-200 bg-white px-3 py-1.5 select-all">{{ $code }}</li>
            @endforeach
        </ul>

        <a href="{{ route('super-admin.dashboard') }}" class="mt-5 inline-block rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-700">I've saved them</a>
    </div>
</x-super-admin.layout>
