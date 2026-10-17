<x-super-admin.layout title="Runs" description="Every workflow and agent run across the platform">
    <x-super-admin.filters :action="route('super-admin.runs.index')" :active="(bool) array_filter($filters)">
        <x-super-admin.filter-select name="status" label="Status" :value="$filters['status'] ?? null" :options="\App\Enums\RunStatus::class" />
        <x-super-admin.filter-text name="trigger_type" label="Trigger" :value="$filters['trigger_type'] ?? null" placeholder="manual, schedule…" class="w-56" />
        <x-super-admin.filter-dates :from="$filters['from'] ?? null" :to="$filters['to'] ?? null" label="Started" />
        <x-super-admin.filter-text name="workspace" label="Workspace" :value="$filters['workspace'] ?? null" placeholder="Paste a workspace ID" mono class="w-full sm:w-96" />
        <x-super-admin.filter-toggle name="include_tests" :checked="$filters['include_tests'] ?? false">Include node tests</x-super-admin.filter-toggle>
    </x-super-admin.filters>

    <x-super-admin.card class="mt-6">
        @include('super-admin.runs.partials.table', ['runs' => $runs, 'empty' => 'No runs match.', 'clear' => array_filter($filters) ? route('super-admin.runs.index') : null])
    </x-super-admin.card>

    <x-super-admin.pagination :paginator="$runs" />
</x-super-admin.layout>
