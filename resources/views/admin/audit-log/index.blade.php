<x-admin.layout title="Audit log">
    <form method="GET" action="{{ route('panel.audit-log.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-56 flex-1">
            <label for="action" class="block text-xs font-medium text-zinc-500">Action starts with</label>
            <input id="action" name="action" type="search" value="{{ $filters['action'] ?? '' }}" placeholder="e.g. admin.login"
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 font-mono text-sm focus:border-zinc-900 focus:outline-none">
        </div>
        <div>
            <label for="actor" class="block text-xs font-medium text-zinc-500">Actor</label>
            <select id="actor" name="actor" class="mt-1 block rounded-md border border-zinc-300 px-3 py-2 text-sm">
                <option value="">Anyone</option>
                <option value="panel" @selected(($filters['actor'] ?? '') === 'panel')>Admin panel</option>
                <option value="platform" @selected(($filters['actor'] ?? '') === 'platform')>Platform admin (API)</option>
                <option value="system" @selected(($filters['actor'] ?? '') === 'system')>System / CLI</option>
            </select>
        </div>
        <div>
            <label for="admin" class="block text-xs font-medium text-zinc-500">Panel admin</label>
            <select id="admin" name="admin" class="mt-1 block rounded-md border border-zinc-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" @selected(($filters['admin'] ?? '') === $admin->id)>{{ $admin->email }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from" class="block text-xs font-medium text-zinc-500">From</label>
            <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="mt-1 block rounded-md border border-zinc-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="to" class="block text-xs font-medium text-zinc-500">To</label>
            <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="mt-1 block rounded-md border border-zinc-300 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-700">Filter</button>
        @if (array_filter($filters))
            <a href="{{ route('panel.audit-log.index') }}" class="py-2 text-sm text-zinc-500 hover:text-zinc-900">Clear</a>
        @endif
    </form>

    <p class="mt-3 text-xs text-zinc-500">
        Quick filters:
        <a href="{{ route('panel.audit-log.index', ['action' => 'admin.login']) }}" class="underline hover:text-zinc-900">admin sign-ins</a> ·
        <a href="{{ route('panel.audit-log.index', ['action' => 'admin.login.failed']) }}" class="underline hover:text-zinc-900">failed sign-ins</a> ·
        <a href="{{ route('panel.audit-log.index', ['action' => 'admin.two_factor']) }}" class="underline hover:text-zinc-900">two-factor events</a> ·
        <a href="{{ route('panel.audit-log.index', ['action' => 'referral_']) }}" class="underline hover:text-zinc-900">referrals</a>
    </p>

    <x-admin.card class="mt-6">
        @include('admin.audit-log.partials.entries', ['entries' => $entries, 'empty' => 'No entries match.'])
    </x-admin.card>

    <div class="mt-4">{{ $entries->links() }}</div>
</x-admin.layout>
