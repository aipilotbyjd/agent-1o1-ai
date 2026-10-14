<x-super-admin.layout title="Audit log" description="Every action taken in this panel, newest first">
    <x-super-admin.filters :action="route('super-admin.audit-log.index')" :active="(bool) array_filter($filters)">
        <x-super-admin.search name="action" label="Action starts with" :value="$filters['action'] ?? ''" placeholder="e.g. super_admin.login" class="min-w-56" />
        <div>
            <label for="super_admin" class="sa-label">Super admin</label>
            <select id="super_admin" name="super_admin" class="sa-input pr-8">
                <option value="">Any</option>
                @foreach ($superAdmins as $superAdmin)
                    <option value="{{ $superAdmin->id }}" @selected(($filters['super_admin'] ?? '') === $superAdmin->id)>{{ $superAdmin->email }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from" class="sa-label">From</label>
            <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="sa-input">
        </div>
        <div>
            <label for="to" class="sa-label">To</label>
            <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="sa-input">
        </div>
        <a href="{{ route('super-admin.audit-log.export', $filters) }}" class="sa-btn-secondary">Export CSV</a>
    </x-super-admin.filters>

    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
        <span class="text-zinc-500">Quick filters</span>
        @foreach (['super_admin.login' => 'Super admin sign-ins', 'super_admin.login.failed' => 'Failed sign-ins', 'super_admin.two_factor' => 'Two-factor events'] as $prefix => $label)
            <a href="{{ route('super-admin.audit-log.index', ['action' => $prefix]) }}" @class([
                'rounded-full px-3 py-1 font-medium ring-1 transition ring-inset',
                'bg-zinc-900 text-white ring-zinc-900' => ($filters['action'] ?? '') === $prefix,
                'bg-white text-zinc-600 ring-zinc-200 hover:text-zinc-900 hover:ring-zinc-300' => ($filters['action'] ?? '') !== $prefix,
            ])>{{ $label }}</a>
        @endforeach
    </div>

    <x-super-admin.card class="mt-6">
        @include('super-admin.audit-log.partials.entries', ['entries' => $entries, 'empty' => 'No entries match.'])
    </x-super-admin.card>

    <div class="mt-4">{{ $entries->links() }}</div>
</x-super-admin.layout>
