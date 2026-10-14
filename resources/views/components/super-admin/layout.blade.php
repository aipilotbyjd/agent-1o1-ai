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
<body class="min-h-full font-sans text-zinc-900 antialiased">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex items-center gap-6">
                <a href="{{ route('super-admin.dashboard') }}" class="text-sm font-semibold">{{ config('app.name') }} SuperAdmin</a>
                <nav class="flex flex-wrap gap-4 text-sm">
                    @foreach ([
                        'Dashboard' => ['super-admin.dashboard', 'super-admin.dashboard'],
                        'Users' => ['super-admin.users.index', 'super-admin.users.*'],
                        'Workspaces' => ['super-admin.workspaces.index', 'super-admin.workspaces.*'],
                        'Runs' => ['super-admin.runs.index', 'super-admin.runs.*'],
                        'Team' => ['super-admin.team.index', 'super-admin.team.*'],
                        'Audit log' => ['super-admin.audit-log.index', 'super-admin.audit-log.*'],
                        'Account' => ['super-admin.account', 'super-admin.account*'],
                    ] as $label => [$routeName, $pattern])
                        <a href="{{ route($routeName) }}" @class(['font-medium text-zinc-900' => request()->routeIs($pattern), 'text-zinc-500 hover:text-zinc-900' => ! request()->routeIs($pattern)])>{{ $label }}</a>
                    @endforeach
                </nav>
            </div>

            <div class="flex items-center gap-3 text-sm">
                <span class="text-zinc-500">{{ auth('super_admin')->user()->email }}</span>
                <form method="POST" action="{{ route('super-admin.logout') }}">
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
