@props(['title', 'description' => null, 'back' => null, 'backLabel' => null])

@php
    $superAdmin = auth('super_admin')->user();
    $failedJobCount = app(\App\Services\SuperAdmin\SuperAdminFailedJobs::class)->count();
    $onAccountPage = request()->routeIs('super-admin.account*');

    /** @var array<string, list<array{0: string, 1: string, 2: string, 3: string|list<string>, 4?: int}>> $navigation */
    $navigation = [
        'Platform' => [
            ['Users', 'user', 'super-admin.users.index', 'super-admin.users.*'],
            ['Workspaces', 'workspace', 'super-admin.workspaces.index', 'super-admin.workspaces.*'],
            ['Runs', 'play', 'super-admin.runs.index', 'super-admin.runs.*'],
        ],
        'Billing' => [
            ['Subscriptions', 'card', 'super-admin.billing.subscriptions.index', 'super-admin.billing.subscriptions.*'],
            ['Credit charges', 'receipt', 'super-admin.billing.credits.index', 'super-admin.billing.credits.*'],
            ['Plan grants', 'gift', 'super-admin.billing.plan-grants.index', 'super-admin.billing.plan-grants.*'],
            ['Plans', 'tag', 'super-admin.billing.plans.index', 'super-admin.billing.plans.*'],
        ],
        'Referrals' => [
            ['Overview', 'chart', 'super-admin.referrals.overview', 'super-admin.referrals.overview'],
            ['Programs', 'megaphone', 'super-admin.referrals.programs.index', 'super-admin.referrals.programs.*'],
            ['Referrals', 'link', 'super-admin.referrals.referrals.index', 'super-admin.referrals.referrals.*'],
            ['Rewards', 'coins', 'super-admin.referrals.rewards.index', 'super-admin.referrals.rewards.*'],
            ['Codes', 'hashtag', 'super-admin.referrals.codes.index', 'super-admin.referrals.codes.*'],
            ['Blocked domains', 'ban', 'super-admin.referrals.blocked-domains.index', 'super-admin.referrals.blocked-domains.*'],
        ],
        'Content' => [
            ['Templates', 'stack', 'super-admin.templates.index', ['super-admin.templates.index', 'super-admin.templates.edit']],
            ['Collections', 'folder', 'super-admin.templates.collections.index', 'super-admin.templates.collections.*'],
            ['Model catalog', 'cpu', 'super-admin.models.index', 'super-admin.models.*'],
        ],
        'System' => [
            ['Queues', 'queue', 'super-admin.system.queues.index', 'super-admin.system.queues.*'],
            ['Failed jobs', 'warning', 'super-admin.system.failed-jobs.index', 'super-admin.system.failed-jobs.*', $failedJobCount],
        ],
        'Security' => [
            ['Team', 'users', 'super-admin.team.index', 'super-admin.team.*'],
            ['Audit log', 'clipboard', 'super-admin.audit-log.index', 'super-admin.audit-log.*'],
        ],
    ];

    $navLinkClasses = fn (bool $active): array => [
        'group relative flex items-center gap-3 rounded-lg px-3 py-1.5 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-indigo-400',
        'bg-white/10 text-white before:absolute before:inset-y-1.5 before:-left-3 before:w-0.5 before:rounded-full before:bg-indigo-400' => $active,
        'text-zinc-400 hover:bg-white/5 hover:text-white' => ! $active,
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
    <a href="#content" class="sr-only z-50 rounded-lg bg-white px-4 py-2 text-sm font-semibold shadow-lg focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Skip to content</a>
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

        <nav id="sa-sidebar-nav" class="flex-1 space-y-4 overflow-y-auto px-3 pt-2 pb-4 [scrollbar-color:var(--color-zinc-800)_transparent] [scrollbar-width:thin]" aria-label="Super admin">
            @php $onDashboard = request()->routeIs('super-admin.dashboard'); @endphp
            <a href="{{ route('super-admin.dashboard') }}" @class($navLinkClasses($onDashboard)) @if ($onDashboard) aria-current="page" @endif>
                <x-super-admin.icon name="dashboard" @class(['size-5', 'text-indigo-400' => $onDashboard, 'text-zinc-500 group-hover:text-zinc-300' => ! $onDashboard]) />
                Dashboard
            </a>

            @foreach ($navigation as $group => $items)
                @php $groupIsActive = collect($items)->contains(fn (array $item): bool => request()->routeIs($item[3])); @endphp
                <details open class="group/section" data-nav-group="{{ $group }}" @if ($groupIsActive) data-nav-active @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-1 text-[11px] font-semibold tracking-wider text-zinc-500 uppercase transition select-none hover:text-zinc-300 focus-visible:outline-2 focus-visible:outline-indigo-400 [&::-webkit-details-marker]:hidden">
                        {{ $group }}
                        <x-super-admin.icon name="chevron-down" class="size-3.5 -rotate-90 transition-transform group-open/section:rotate-0" />
                    </summary>
                    <ul class="mt-1 space-y-0.5">
                        @foreach ($items as $item)
                            @php
                                [$label, $icon, $routeName, $pattern] = $item;
                                $badge = $item[4] ?? 0;
                                $active = request()->routeIs($pattern);
                            @endphp
                            <li>
                                <a href="{{ route($routeName) }}" @class($navLinkClasses($active)) @if ($active) aria-current="page" @endif>
                                    <x-super-admin.icon :name="$icon" @class(['size-5', 'text-indigo-400' => $active, 'text-zinc-500 group-hover:text-zinc-300' => ! $active]) />
                                    <span class="flex-1 truncate">{{ $label }}</span>
                                    @if ($badge > 0)
                                        <span class="rounded-full bg-red-500/15 px-1.5 py-0.5 text-[11px] leading-none font-semibold text-red-300 tabular-nums ring-1 ring-red-500/25 ring-inset" title="{{ $badge }} {{ str('failed job')->plural($badge) }}">{{ $badge > 99 ? '99+' : $badge }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-3">
            <div @class(['flex items-center gap-1 rounded-lg transition', 'bg-white/10' => $onAccountPage])>
                <a href="{{ route('super-admin.account') }}" class="group flex min-w-0 flex-1 items-center gap-3 rounded-lg px-2 py-2 transition hover:bg-white/5 focus-visible:outline-2 focus-visible:outline-indigo-400" title="Account settings" @if ($onAccountPage) aria-current="page" @endif>
                    <x-super-admin.avatar :name="$superAdmin->name ?: $superAdmin->email" class="ring-2 ring-white/10" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-white">{{ $superAdmin->name }}</span>
                        <span class="block truncate text-xs text-zinc-500 group-hover:text-zinc-400">{{ $superAdmin->email }}</span>
                    </span>
                </a>
                <form method="POST" action="{{ route('super-admin.logout') }}" class="pr-1">
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
            <span class="truncate text-sm font-semibold">{{ $title }}</span>
        </div>

        <main id="content" tabindex="-1" class="mx-auto focus:outline-none max-w-7xl px-4 py-8 sm:px-6 lg:px-10 lg:py-10">
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
                <div class="mt-6 flex items-center gap-2.5 rounded-xl bg-emerald-50 py-3 pr-2 pl-4 text-sm text-emerald-800 ring-1 ring-emerald-600/15" role="status" data-flash>
                    <x-super-admin.icon name="check-circle" class="size-5 text-emerald-600" />
                    <span class="flex-1">{{ session('status') }}</span>
                    <button type="button" class="rounded-md p-1 text-emerald-700/70 transition hover:bg-emerald-100 hover:text-emerald-900" data-dismiss-flash>
                        <x-super-admin.icon name="close" class="size-4" />
                        <span class="sr-only">Dismiss</span>
                    </button>
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

        // Copy buttons, dismissible flash messages and filters that apply as soon as a choice changes.
        document.addEventListener('click', (event) => {
            const copyButton = event.target.closest('[data-copy]');

            if (copyButton) {
                event.preventDefault();
                navigator.clipboard?.writeText(copyButton.dataset.copy).then(() => {
                    copyButton.querySelector('[data-copy-idle]')?.classList.add('hidden');
                    copyButton.querySelector('[data-copy-done]')?.classList.remove('hidden');
                    setTimeout(() => {
                        copyButton.querySelector('[data-copy-idle]')?.classList.remove('hidden');
                        copyButton.querySelector('[data-copy-done]')?.classList.add('hidden');
                    }, 1500);
                });
            }

            event.target.closest('[data-dismiss-flash]')?.closest('[data-flash]')?.remove();
        });

        document.addEventListener('change', (event) => {
            const form = event.target.closest('form[data-auto-submit]');

            if (form && event.target.matches('select, input[type="checkbox"], input[type="date"]')) {
                form.requestSubmit();
            }
        });

        document.addEventListener('keydown', (event) => {
            const search = document.querySelector('[data-search-shortcut]');
            const typing = event.target.closest('input, textarea, select, [contenteditable]');

            if (search && event.key === '/' && ! typing && ! event.metaKey && ! event.ctrlKey && ! event.altKey) {
                event.preventDefault();
                search.focus();
                search.select();
            }
        });

        // Fields marked data-show-when="field:value1,value2" only show while that field holds one of those values.
        const syncDependentFields = (form) => {
            form.querySelectorAll('[data-show-when]').forEach((element) => {
                const [name, values] = element.dataset.showWhen.split(':');
                const control = form.elements.namedItem(name);

                if (control && ! element.classList.contains('sa-field-invalid')) {
                    element.hidden = ! values.split(',').includes(control.value);
                }
            });
        };

        document.querySelectorAll('form:has([data-show-when])').forEach((form) => {
            syncDependentFields(form);
            form.addEventListener('change', () => syncDependentFields(form));
        });

        // Remember collapsed sidebar sections and the sidebar's scroll position between pages.
        (() => {
            const nav = document.getElementById('sa-sidebar-nav');
            const storage = (type) => { try { return window[type]; } catch { return null; } };
            const local = storage('localStorage');
            const session = storage('sessionStorage');
            let collapsed = [];

            try { collapsed = JSON.parse(local?.getItem('sa-nav-collapsed') ?? '[]'); } catch {}

            nav.querySelectorAll('details[data-nav-group]').forEach((section) => {
                if (collapsed.includes(section.dataset.navGroup) && ! section.hasAttribute('data-nav-active')) {
                    section.open = false;
                }

                section.addEventListener('toggle', () => {
                    collapsed = collapsed.filter((group) => group !== section.dataset.navGroup);

                    if (! section.open) {
                        collapsed.push(section.dataset.navGroup);
                    }

                    try { local?.setItem('sa-nav-collapsed', JSON.stringify(collapsed)); } catch {}
                });
            });

            const savedScroll = Number(session?.getItem('sa-nav-scroll'));

            if (savedScroll) {
                nav.scrollTop = savedScroll;
            }

            nav.querySelector('[aria-current="page"]')?.scrollIntoView({ block: 'nearest' });

            window.addEventListener('pagehide', () => {
                try { session?.setItem('sa-nav-scroll', String(nav.scrollTop)); } catch {}
            });
        })();
    </script>
</body>
</html>
