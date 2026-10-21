<x-super-admin.layout title="Recovery codes" description="Save these before you leave this page">
    <x-super-admin.card class="max-w-xl">
        <div class="px-6 py-6">
            <div class="flex gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-600/20">
                <x-super-admin.icon name="warning" class="mt-0.5 size-5 text-amber-600" />
                <p>Store these somewhere safe, such as a password manager. Each code works once if you lose your authenticator. <strong>They won't be shown again.</strong></p>
            </div>

            <ul class="mt-5 grid grid-cols-1 gap-2 font-mono text-sm sm:grid-cols-2">
                @foreach ($recoveryCodes as $code)
                    <li class="rounded-lg bg-zinc-50 px-3 py-2 text-center text-zinc-800 ring-1 ring-zinc-200 select-all">{{ $code }}</li>
                @endforeach
            </ul>

            <a href="{{ route('super-admin.dashboard') }}" class="sa-btn-primary mt-6">I've saved them</a>
        </div>
    </x-super-admin.card>
</x-super-admin.layout>
