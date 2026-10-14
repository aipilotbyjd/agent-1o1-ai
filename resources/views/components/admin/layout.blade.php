@props(['title'])

<!DOCTYPE html>
<html lang="en" class="h-full bg-zinc-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · {{ config('app.name') }} Admin</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-full font-sans text-zinc-900 antialiased">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex items-center gap-6">
                <a href="{{ route('panel.dashboard') }}" class="text-sm font-semibold">{{ config('app.name') }} Admin</a>
                <nav class="flex gap-4 text-sm">
                    <a href="{{ route('panel.dashboard') }}" @class(['text-zinc-900 font-medium' => request()->routeIs('panel.dashboard'), 'text-zinc-500 hover:text-zinc-900' => ! request()->routeIs('panel.dashboard')])>Dashboard</a>
                    <a href="{{ route('panel.account') }}" @class(['text-zinc-900 font-medium' => request()->routeIs('panel.account'), 'text-zinc-500 hover:text-zinc-900' => ! request()->routeIs('panel.account')])>Account</a>
                </nav>
            </div>

            <div class="flex items-center gap-3 text-sm">
                <span class="text-zinc-500">{{ auth('admin')->user()->email }}</span>
                <form method="POST" action="{{ route('panel.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-zinc-300 px-3 py-1.5 font-medium hover:bg-zinc-100">Sign out</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        <h1 class="text-xl font-semibold">{{ $title }}</h1>

        @if (session('status'))
            <p class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>
        @endif

        <div class="mt-6">
            {{ $slot }}
        </div>
    </main>
</body>
</html>
