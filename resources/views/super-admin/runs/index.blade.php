<x-super-admin.layout title="Runs">
    <form method="GET" action="{{ route('super-admin.runs.index') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label for="status" class="block text-xs font-medium text-zinc-500">Status</label>
            <select id="status" name="status" class="mt-1 block rounded-md border border-zinc-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                @foreach (\App\Enums\RunStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str_replace('_', ' ', $status->value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-72">
            <label for="workspace" class="block text-xs font-medium text-zinc-500">Workspace ID</label>
            <input id="workspace" name="workspace" type="text" value="{{ $filters['workspace'] ?? '' }}"
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 font-mono text-sm focus:border-zinc-900 focus:outline-none">
        </div>
        <div>
            <label for="trigger_type" class="block text-xs font-medium text-zinc-500">Trigger</label>
            <input id="trigger_type" name="trigger_type" type="text" value="{{ $filters['trigger_type'] ?? '' }}" placeholder="manual, schedule…"
                class="mt-1 block w-36 rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none">
        </div>
        <div>
            <label for="from" class="block text-xs font-medium text-zinc-500">From</label>
            <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="mt-1 block rounded-md border border-zinc-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="to" class="block text-xs font-medium text-zinc-500">To</label>
            <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="mt-1 block rounded-md border border-zinc-300 px-3 py-2 text-sm">
        </div>
        <label class="flex items-center gap-2 py-2 text-sm">
            <input type="checkbox" name="include_tests" value="1" @checked($filters['include_tests'] ?? false)>
            Include node tests
        </label>
        <button type="submit" class="rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-700">Filter</button>
        @if (array_filter($filters))
            <a href="{{ route('super-admin.runs.index') }}" class="py-2 text-sm text-zinc-500 hover:text-zinc-900">Clear</a>
        @endif
    </form>

    <x-super-admin.card class="mt-6">
        @include('super-admin.runs.partials.table', ['runs' => $runs, 'empty' => 'No runs match.'])
    </x-super-admin.card>

    <div class="mt-4">{{ $runs->links() }}</div>
</x-super-admin.layout>
