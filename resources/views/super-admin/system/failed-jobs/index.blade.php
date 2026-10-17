<x-super-admin.layout title="Failed jobs" description="Queue jobs that ran out of attempts. Payloads are never shown: they hold customer data.">
    <x-super-admin.filters :action="route('super-admin.system.failed-jobs.index')" :active="(bool) array_filter($filters)">
        <x-super-admin.filter-select name="queue" label="Queue" :value="$filters['queue'] ?? null" :options="collect($queues)->mapWithKeys(fn (string $queue): array => [$queue => $queue])" />
    </x-super-admin.filters>

    <x-super-admin.card class="mt-6">
        @if ($jobs->isEmpty())
            <x-super-admin.empty :clear="array_filter($filters) ? route('super-admin.system.failed-jobs.index') : null" icon="check-circle">No failed jobs.</x-super-admin.empty>
        @else
            <div class="overflow-x-auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Queue</th>
                            <th>Error</th>
                            <th>Failed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($jobs as $job)
                            <tr class="align-top">
                                <td>
                                    <a href="{{ route('super-admin.system.failed-jobs.show', $job->uuid) }}" class="sa-row-link">{{ class_basename($job->job) }}</a>
                                    <p class="flex items-center gap-0.5 font-mono text-xs text-zinc-400">{{ \Illuminate\Support\Str::limit($job->uuid, 8, '') }} <x-super-admin.copy :value="$job->uuid" label="Copy job UUID" /></p>
                                </td>
                                <td class="font-mono text-xs">{{ $job->queue }}</td>
                                <td class="max-w-xl text-xs text-red-700">{{ $job->exception_summary }}</td>
                                <td class="whitespace-nowrap text-zinc-500" title="{{ $job->failed_at }}">{{ \Illuminate\Support\Carbon::parse($job->failed_at)->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-super-admin.card>

    <x-super-admin.pagination :paginator="$jobs" />
</x-super-admin.layout>
