@props(['title'])

<!DOCTYPE html>
<html lang="en" class="h-full bg-zinc-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · {{ config('app.name') }} SuperAdmin</title>
    @vite('resources/css/app.css')
</head>
<body class="flex h-full items-center justify-center px-4 py-12 font-sans text-zinc-900 antialiased">
    <main class="w-full max-w-sm">
        <p class="mb-6 text-center text-sm font-semibold tracking-wide text-zinc-500 uppercase">{{ config('app.name') }} SuperAdmin</p>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h1 class="text-lg font-semibold">{{ $title }}</h1>

            @if (session('status'))
                <p class="mt-3 rounded-md bg-zinc-100 px-3 py-2 text-sm text-zinc-700">{{ session('status') }}</p>
            @endif

            <div class="mt-5">
                {{ $slot }}
            </div>
        </div>
    </main>
</body>
</html>
