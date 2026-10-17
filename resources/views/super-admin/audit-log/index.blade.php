<x-super-admin.layout title="Audit log" description="Every action taken in this panel, newest first">
    <x-slot:actions>
        <a href="{{ route('super-admin.audit-log.export', $filters) }}" class="sa-btn-secondary">
            <x-super-admin.icon name="arrow-down" class="size-4" />
            Export CSV
        </a>
    </x-slot:actions>

    <x-super-admin.filters :action="route('super-admin.audit-log.index')" :active="(bool) array_filter($filters)">
        <x-super-admin.search name="action" label="Action starts with" :value="$filters['action'] ?? ''" placeholder="Action starts with… e.g. super_admin.login" />
        <x-super-admin.filter-select name="super_admin" label="Super admin" :value="$filters['super_admin'] ?? null" :options="$superAdmins->pluck('email', 'id')" />
        <x-super-admin.filter-dates :from="$filters['from'] ?? null" :to="$filters['to'] ?? null" />
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
        @include('super-admin.audit-log.partials.entries', ['entries' => $entries, 'empty' => 'No entries match.', 'clear' => array_filter($filters) ? route('super-admin.audit-log.index') : null])
    </x-super-admin.card>

    <x-super-admin.pagination :paginator="$entries" />
</x-super-admin.layout>
