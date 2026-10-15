@props(['title', 'description' => null, 'back' => null, 'backLabel' => null])

@php
    $superAdmin = auth('super_admin')->user();
    $navigation = [
        'Overview' => [
            ['Dashboard', 'dashboard', 'super-admin.dashboard', 'super-admin.dashboard'],
        ],
        'Platform' => [
            ['Users', 'user', 'super-admin.users.index', 'super-admin.users.*'],
            ['Workspaces', 'workspace', 'super-admin.workspaces.index', 'super-admin.workspaces.*'],
            ['Runs', 'bolt', 'super-admin.runs.index', 'super-admin.runs.*'],
        ],
        'Billing' => [
            ['Subscriptions', 'card', 'super-admin.billing.subscriptions.index', 'super-admin.billing.subscriptions.*'],
            ['Credit charges', 'coins', 'super-admin.billing.credits.index', 'super-admin.billing.credits.*'],
            ['Plan grants', 'gift', 'super-admin.billing.plan-grants.index', 'super-admin.billing.plan-grants.*'],
            ['Plans', 'tag', 'super-admin.billing.plans.index', 'super-admin.billing.plans.*'],
        ],
        'Referrals' => [
            ['Overview', 'gift', 'super-admin.referrals.overview', 'super-admin.referrals.overview'],
            ['Programs', 'tag', 'super-admin.referrals.programs.index', 'super-admin.referrals.programs.*'],
            ['Referrals', 'user', 'super-admin.referrals.referrals.index', 'super-admin.referrals.referrals.*'],
            ['Rewards', 'coins', 'super-admin.referrals.rewards.index', 'super-admin.referrals.rewards.*'],
            ['Codes', 'queue', 'super-admin.referrals.codes.index', 'super-admin.referrals.codes.*'],
            ['Blocked domains', 'lock', 'super-admin.referrals.blocked-domains.index', 'super-admin.referrals.blocked-domains.*'],
        ],
        'Content' => [
            ['Templates', 'dashboard', 'super-admin.templates.index', 'super-admin.templates.index'],
            ['Collections', 'inbox', 'super-admin.templates.collections.index', 'super-admin.templates.collections.*'],
            ['Model catalog', 'bolt', 'super-admin.models.index', 'super-admin.models.*'],
        ],
        'System' => [
            ['Queues', 'queue', 'super-admin.system.queues.index', 'super-admin.system.queues.*'],
            ['Failed jobs', 'warning', 'super-admin.system.failed-jobs.index', 'super-admin.system.failed-jobs.*'],
        ],
        'Security' => [
            ['Team', 'account', 'super-admin.team.index', 'super-admin.team.*'],
            ['Audit log', 'shield', 'super-admin.audit-log.index', 'super-admin.audit-log.*'],
            ['Account', 'account', 'super-admin.account', 'super-admin.account*'],
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="en" class="h-full bg-zinc-100/70">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · {{ config('app.name') }} SuperAdmin</title>
    @fonts
    @vite('resources/css/app.css')
    @stack('styles')
</head>
<body class="min-h-full font-sans text-zinc-900 antialiased">
    <input type="checkbox" id="sa-nav" class="peer sr-only">

    <label for="sa-nav" class="fixed inset-0 z-30 hidden bg-zinc-950/40 backdrop-blur-sm peer-checked:block lg:peer-checked:hidden" aria-hidden="true"></label>

    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-zinc-950 text-zinc-300 transition-transform duration-200 peer-checked:translate-x-0 lg:translate-x-0">
        <div class="flex h-16 items-center justify-between gap-3 px-5">
            <a href="{{ route('super-admin.dashboard') }}" class="flex items-center gap-2.5">
                <span class="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-400 to-violet-600 text-white shadow-lg shadow-indigo-500/20">
                    <x-super-admin.icon name="shield" class="size-4.5" />
                </span>
                <span class="leading-tight">
                    <span class="block text-sm font-semibold text-white">{{ config('app.name') }}</span>
                    <span class="block text-[11px] font-medium tracking-wide text-zinc-500 uppercase">Super admin</span>
                </span>
            </a>
            <label for="sa-nav" class="cursor-pointer rounded-md p-1 text-zinc-500 hover:text-white lg:hidden">
                <x-super-admin.icon name="close" class="size-5" />
                <span class="sr-only">Close menu</span>
            </label>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
            @foreach ($navigation as $group => $items)
                <div>
                    <p class="px-3 text-[11px] font-semibold tracking-wider text-zinc-500 uppercase">{{ $group }}</p>
                    <ul class="mt-2 space-y-0.5">
                        @foreach ($items as [$label, $icon, $routeName, $pattern])
                            @php $active = request()->routeIs($pattern); @endphp
                            <li>
                                <a href="{{ route($routeName) }}" @class([
                                    'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                                    'bg-white/10 text-white' => $active,
                                    'text-zinc-400 hover:bg-white/5 hover:text-white' => ! $active,
                                ]) @if ($active) aria-current="page" @endif>
                                    <x-super-admin.icon :name="$icon" @class(['size-5', 'text-indigo-400' => $active, 'text-zinc-500 group-hover:text-zinc-300' => ! $active]) />
                                    {{ $label }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-3">
            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                <x-super-admin.avatar :name="$superAdmin->name ?: $superAdmin->email" class="ring-2 ring-white/10" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-white">{{ $superAdmin->name }}</p>
                    <p class="truncate text-xs text-zinc-500">{{ $superAdmin->email }}</p>
                </div>
                <form method="POST" action="{{ route('super-admin.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md p-1.5 text-zinc-500 transition hover:bg-white/10 hover:text-white" title="Sign out">
                        <x-super-admin.icon name="logout" class="size-5" />
                        <span class="sr-only">Sign out</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="lg:pl-64">
        <div class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-zinc-200 bg-white/80 px-4 backdrop-blur lg:hidden">
            <label for="sa-nav" class="cursor-pointer rounded-md p-1.5 text-zinc-600 hover:bg-zinc-100">
                <x-super-admin.icon name="menu" class="size-5" />
                <span class="sr-only">Open menu</span>
            </label>
            <span class="text-sm font-semibold">{{ config('app.name') }} Super admin</span>
        </div>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-10 lg:py-10">
            @if ($back)
                <a href="{{ $back }}" class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-zinc-500 transition hover:text-zinc-900">
                    <x-super-admin.icon name="chevron-left" class="size-4" />
                    {{ $backLabel ?? 'Back' }}
                </a>
            @endif

            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="truncate text-2xl font-semibold tracking-tight text-zinc-950">{{ $title }}</h1>
                    @if ($description)
                        <p class="mt-1 text-sm text-zinc-500">{{ $description }}</p>
                    @endif
                </div>
                @isset($actions)
                    <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                @endisset
            </div>

            @if (session('status'))
                <div class="mt-6 flex items-center gap-2.5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-600/15">
                    <x-super-admin.icon name="check-circle" class="size-5 text-emerald-600" />
                    {{ session('status') }}
                </div>
            @endif

            <div class="mt-8">
                {{ $slot }}
            </div>
        </main>
    </div>

    {{-- Inline handlers are blocked by the CSP, so confirmations are wired here. --}}
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        document.addEventListener('submit', (event) => {
            const message = event.submitter?.dataset.confirm ?? event.target.dataset.confirm;

            if (message && ! window.confirm(message)) {
                event.preventDefault();
            }
        });
    </script>
</body>
</html>
