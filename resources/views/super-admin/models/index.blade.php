<x-super-admin.layout title="Model catalog" description="The models people pick in agents and workflows, and the providers that run them">
    <x-slot:actions>
        <a href="{{ route('super-admin.models.create') }}" class="sa-btn-primary">Add model</a>
    </x-slot:actions>

    <x-super-admin.card>
        @if ($entries->isEmpty())
            <x-super-admin.empty icon="bolt">The catalog is empty. Run the model catalog seeder or add a model.</x-super-admin.empty>
        @else
            <div class="overflow-x-auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Brand</th>
                            <th>Status</th>
                            <th class="text-right">Routes</th>
                            <th class="text-right">Agents</th>
                            <th>Retires</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr class="hover:bg-zinc-50/70">
                                <td>
                                    <a href="{{ route('super-admin.models.show', $entry) }}" class="sa-link">{{ $entry->display_name }}</a>
                                    <p class="font-mono text-xs text-zinc-400">{{ $entry->slug }}</p>
                                </td>
                                <td class="capitalize">{{ $entry->brand }}</td>
                                <td class="space-x-1">
                                    <x-super-admin.status-badge :status="$entry->is_active ? 'active' : 'disabled'" />
                                    @if ($entry->is_internal)
                                        <x-super-admin.status-badge status="internal" />
                                    @endif
                                    @if ($entry->is_active && $entry->enabled_routes_count === 0)
                                        <x-super-admin.status-badge status="failed" title="No enabled route: prompts on this model will fail" />
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $entry->enabled_routes_count }} / {{ $entry->routes_count }}</td>
                                <td class="text-right tabular-nums">{{ number_format($entry->agents_count) }}</td>
                                <td class="whitespace-nowrap text-zinc-500">{{ $entry->retires_at?->toFormattedDateString() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-super-admin.card>
</x-super-admin.layout>
