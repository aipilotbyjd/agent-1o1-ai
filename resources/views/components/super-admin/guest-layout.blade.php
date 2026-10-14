@props(['title', 'description' => null])

<!DOCTYPE html>
<html lang="en" class="h-full bg-zinc-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · {{ config('app.name') }} SuperAdmin</title>
    @fonts
    @vite('resources/css/app.css')
</head>
<body class="relative flex min-h-full items-center justify-center overflow-hidden px-4 py-12 font-sans text-zinc-900 antialiased">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute -top-40 left-1/2 size-[42rem] -translate-x-1/2 rounded-full bg-indigo-600/25 blur-3xl"></div>
        <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.04)_1px,transparent_1px)] bg-[size:48px_48px] [mask-image:radial-gradient(ellipse_at_center,black_30%,transparent_75%)]"></div>
    </div>

    <main class="relative w-full max-w-sm">
        <div class="mb-8 flex flex-col items-center gap-3 text-center">
            <span class="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-400 to-violet-600 text-white shadow-lg shadow-indigo-500/30">
                <x-super-admin.icon name="shield" class="size-6" />
            </span>
            <p class="text-sm font-semibold text-white">{{ config('app.name') }} <span class="font-normal text-zinc-400">Super admin</span></p>
        </div>

        <div class="rounded-2xl bg-white p-7 shadow-2xl shadow-black/40 ring-1 ring-white/10">
            <h1 class="text-lg font-semibold tracking-tight">{{ $title }}</h1>
            @if ($description)
                <p class="mt-1.5 text-sm text-zinc-500">{{ $description }}</p>
            @endif

            @if (session('status'))
                <p class="mt-4 rounded-lg bg-zinc-100 px-3 py-2 text-sm text-zinc-700">{{ session('status') }}</p>
            @endif

            <div class="mt-6">
                {{ $slot }}
            </div>
        </div>

        <p class="mt-6 flex items-center justify-center gap-1.5 text-xs text-zinc-500">
            <x-super-admin.icon name="lock" class="size-3.5" />
            Restricted area. Every sign-in is recorded.
        </p>
    </main>
</body>
</html>
