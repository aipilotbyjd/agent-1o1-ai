<?php

namespace App\Services\SuperAdmin;

use App\Models\Runs\Run;
use App\Services\Workflows\NodeTester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Read-only lookups of runs for the super admin panel. Run and node inputs
 * and outputs are customer data and are deliberately not loaded — only
 * status, timing and error messages.
 */
class SuperAdminRunDirectory
{
    private const int PER_PAGE = 50;

    /**
     * @param  array<string, mixed>  $filters  validated `IndexRunsRequest` input
     * @return LengthAwarePaginator<int, Run>
     */
    public function search(array $filters, bool $includeTests): LengthAwarePaginator
    {
        return Run::query()
            ->whereNull('parent_run_id')
            ->unless($includeTests, fn (Builder $query) => $query->where('trigger_type', '!=', NodeTester::TRIGGER_TYPE))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['workspace'] ?? null, fn (Builder $query, string $workspace) => $query->where('workspace_id', $workspace))
            ->when($filters['trigger_type'] ?? null, fn (Builder $query, string $type) => $query->where('trigger_type', $type))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->with(['workspace' => fn ($query) => $query->withTrashed()->select('id', 'name'), 'workflow' => fn ($query) => $query->withTrashed()->select('id', 'name')])
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    public function detail(Run $run): Run
    {
        $run->load([
            'workspace' => fn ($query) => $query->withTrashed()->select('id', 'name'),
            'workflow' => fn ($query) => $query->withTrashed()->select('id', 'name'),
            'triggeredBy:id,name,email',
            'parentRun:id,status',
            'retriedFrom:id,status',
            'nodeRuns' => fn ($query) => $query->select('id', 'run_id', 'key', 'type', 'status', 'attempt', 'max_attempts', 'error', 'started_at', 'finished_at')->orderBy('created_at'),
        ]);
        $run->loadCount('childRuns');

        return $run;
    }
}
