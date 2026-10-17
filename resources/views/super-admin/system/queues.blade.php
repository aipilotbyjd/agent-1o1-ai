<x-super-admin.layout title="Queues" description="Jobs waiting on each application queue">
    <x-slot:actions>
        <a href="{{ route('super-admin.system.failed-jobs.index') }}" class="sa-btn-secondary">
            <x-super-admin.icon name="warning" class="size-4" />
            Failed jobs
        </a>
    </x-slot:actions>

    <x-super-admin.card title="Application queues" :description="'Connection: '.$connection">
        <div class="overflow-x-auto">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th>Queue</th>
                        <th class="text-right">Waiting</th>
                        <th class="text-right">Failed</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sizes as $queue => $size)
                        <tr>
                            <td class="font-mono text-xs">{{ $queue }}</td>
                            <td class="text-right tabular-nums">
                                @if ($size === null)
                                    <span class="text-zinc-400" title="The queue backend did not answer">unknown</span>
                                @else
                                    {{ number_format($size) }}
                                @endif
                            </td>
                            <td class="text-right tabular-nums">
                                @if ($failedCounts[$queue] ?? 0)
                                    <a href="{{ route('super-admin.system.failed-jobs.index', ['queue' => $queue]) }}" class="font-semibold text-red-700 hover:underline">{{ number_format($failedCounts[$queue]) }}</a>
                                @else
                                    <span class="text-zinc-400">0</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-super-admin.card>
</x-super-admin.layout>
