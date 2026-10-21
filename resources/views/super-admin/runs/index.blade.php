<x-super-admin.layout title="Runs" description="Every workflow and agent run across the platform">
    <x-super-admin.filters :action="route('super-admin.runs.index')" :active="(bool) array_filter($filters)">
        <div>
            <label for="status" class="sa-label">Status</label>
            <select id="status" name="status" class="sa-input pr-8">
                <option value="">Any</option>
                @foreach (\App\Enums\RunStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str_replace('_', ' ', $status->value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-72 flex-1">
            <label for="workspace" class="sa-label">Workspace ID</label>
            <input id="workspace" name="workspace" type="text" value="{{ $filters['workspace'] ?? '' }}" class="sa-input font-mono">
        </div>
        <div>
            <label for="trigger_type" class="sa-label">Trigger</label>
            <input id="trigger_type" name="trigger_type" type="text" value="{{ $filters['trigger_type'] ?? '' }}" placeholder="manual, schedule…" class="sa-input w-36">
        </div>
        <div>
            <label for="from" class="sa-label">From</label>
            <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="sa-input">
        </div>
        <div>
            <label for="to" class="sa-label">To</label>
            <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="sa-input">
        </div>
        <label class="flex items-center gap-2 py-2 text-sm text-zinc-700">
            <input type="checkbox" name="include_tests" value="1" class="size-4 rounded border-zinc-300 accent-zinc-900" @checked($filters['include_tests'] ?? false)>
            Include node tests
        </label>
    </x-super-admin.filters>

    <x-super-admin.card class="mt-6">
        @include('super-admin.runs.partials.table', ['runs' => $runs, 'empty' => 'No runs match.'])
    </x-super-admin.card>

    <div class="mt-4">{{ $runs->links() }}</div>
</x-super-admin.layout>
