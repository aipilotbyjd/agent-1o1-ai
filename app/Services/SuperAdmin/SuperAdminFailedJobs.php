<?php

namespace App\Services\SuperAdmin;

use App\Enums\Queue as QueueName;
use App\Models\SuperAdmin\SuperAdmin;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

/**
 * Failed queue jobs and queue sizes for the super admin panel.
 *
 * A job's payload holds the serialized job — customer data, sometimes
 * credentials — so only its display name, queue and exception are shown,
 * never the payload itself.
 */
class SuperAdminFailedJobs
{
    private const int PER_PAGE = 50;

    private const int EXCEPTION_PREVIEW_LENGTH = 20_000;

    public function __construct(
        private readonly FailedJobProviderInterface $failer,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    /**
     * @return LengthAwarePaginator<int, object{uuid: string, connection: string, queue: string, job: string, exception_summary: string, failed_at: string}>
     */
    public function paginate(?string $queue = null): LengthAwarePaginator
    {
        $page = $this->table()
            ->when($queue, fn ($query, string $queue) => $query->where('queue', $queue))
            ->orderByDesc('failed_at')
            ->paginate(self::PER_PAGE, ['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->withQueryString();

        $page->getCollection()->transform(fn (object $row): object => (object) [
            'uuid' => $row->uuid,
            'connection' => $row->connection,
            'queue' => $row->queue,
            'job' => $this->displayName($row->payload),
            'exception_summary' => Str::limit(strtok((string) $row->exception, "\n") ?: '', 200),
            'failed_at' => $row->failed_at,
        ]);

        return $page;
    }

    /**
     * @return object{uuid: string, connection: string, queue: string, job: string, exception: string, failed_at: string}|null
     */
    public function find(string $uuid): ?object
    {
        $row = $this->table()->where('uuid', $uuid)->first(['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at']);

        if ($row === null) {
            return null;
        }

        return (object) [
            'uuid' => $row->uuid,
            'connection' => $row->connection,
            'queue' => $row->queue,
            'job' => $this->displayName($row->payload),
            'exception' => Str::limit((string) $row->exception, self::EXCEPTION_PREVIEW_LENGTH),
            'failed_at' => $row->failed_at,
        ];
    }

    public function count(): int
    {
        return $this->table()->count();
    }

    /**
     * Pushes the job back onto its queue, the same as `queue:retry`.
     */
    public function retry(string $uuid, SuperAdmin $actor): bool
    {
        $job = $this->find($uuid);

        if ($job === null) {
            return false;
        }

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        $this->audit->record($actor, 'failed_job.retried', null, null, ['uuid' => $uuid, 'job' => $job->job, 'queue' => $job->queue]);

        return true;
    }

    public function forget(string $uuid, SuperAdmin $actor): bool
    {
        $job = $this->find($uuid);

        if ($job === null || ! $this->failer->forget($uuid)) {
            return false;
        }

        $this->audit->record($actor, 'failed_job.deleted', null, null, ['uuid' => $uuid, 'job' => $job->job, 'queue' => $job->queue]);

        return true;
    }

    /**
     * Pending jobs per application queue on the default connection, or null
     * where the driver can't say (or the queue backend is unreachable).
     *
     * @return array<string, int|null>
     */
    public function queueSizes(): array
    {
        return collect(QueueName::cases())
            ->mapWithKeys(function (QueueName $queue): array {
                try {
                    return [$queue->value => Queue::size($queue->value)];
                } catch (Throwable) {
                    return [$queue->value => null];
                }
            })
            ->all();
    }

    /**
     * Failed-job counts per queue.
     *
     * @return array<string, int>
     */
    public function failedCountsByQueue(): array
    {
        return $this->table()
            ->select('queue')
            ->selectRaw('count(*) as failed_count')
            ->groupBy('queue')
            ->pluck('failed_count', 'queue')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function failedQueues(): array
    {
        return $this->table()->distinct()->orderBy('queue')->pluck('queue')->all();
    }

    private function displayName(?string $payload): string
    {
        $decoded = json_decode((string) $payload, true);

        return is_array($decoded) ? (string) ($decoded['displayName'] ?? $decoded['job'] ?? 'Unknown job') : 'Unknown job';
    }

    private function table(): Builder
    {
        return DB::connection(config('queue.failed.database'))->table((string) config('queue.failed.table'));
    }
}
