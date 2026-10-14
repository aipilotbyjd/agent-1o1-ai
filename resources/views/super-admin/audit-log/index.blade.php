<x-super-admin.layout title="Audit log">
    <form method="GET" action="{{ route('super-admin.audit-log.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-56 flex-1">
            <label for="action" class="block text-xs font-medium text-zinc-500">Action starts with</label>
            <input id="action" name="action" type="search" value="{{ $filters['action'] ?? '' }}" placeholder="e.g. super_admin.login"
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 font-mono text-sm focus:border-zinc-900 focus:outline-none">
        </div>
        <div>
            <label for="super_admin" class="block text-xs font-medium text-zinc-500">Super admin</label>
            <select id="super_admin" name="super_admin" class="mt-1 block rounded-md border border-zinc-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                @foreach ($superAdmins as $superAdmin)
                    <option value="{{ $superAdmin->id }}" @selected(($filters['super_admin'] ?? '') === $superAdmin->id)>{{ $superAdmin->email }}</option>
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
        <a href="{{ route('super-admin.audit-log.export', $filters) }}" class="py-2 text-sm text-zinc-500 hover:text-zinc-900">Export CSV</a>
        @if (array_filter($filters))
            <a href="{{ route('super-admin.audit-log.index') }}" class="py-2 text-sm text-zinc-500 hover:text-zinc-900">Clear</a>
        @endif
    </form>

    <p class="mt-3 text-xs text-zinc-500">
        Quick filters:
        <a href="{{ route('super-admin.audit-log.index', ['action' => 'super_admin.login']) }}" class="underline hover:text-zinc-900">super admin sign-ins</a> ·
        <a href="{{ route('super-admin.audit-log.index', ['action' => 'super_admin.login.failed']) }}" class="underline hover:text-zinc-900">failed sign-ins</a> ·
        <a href="{{ route('super-admin.audit-log.index', ['action' => 'super_admin.two_factor']) }}" class="underline hover:text-zinc-900">two-factor events</a>
    </p>

    <x-super-admin.card class="mt-6">
        @include('super-admin.audit-log.partials.entries', ['entries' => $entries, 'empty' => 'No entries match.'])
    </x-super-admin.card>

    <div class="mt-4">{{ $entries->links() }}</div>
</x-super-admin.layout>
