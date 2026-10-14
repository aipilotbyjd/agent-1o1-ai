<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexRunsRequest;
use App\Models\Runs\Run;
use App\Services\Workflows\NodeTester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Platform-wide run list for debugging failures. Run and node inputs and
 * outputs are customer data and are deliberately not shown — only status,
 * timing and error messages.
 */
class RunController extends Controller
{
    private const int PER_PAGE = 50;

    public function index(IndexRunsRequest $request): View
    {
        $filters = $request->validated();

        $runs = Run::query()
            ->whereNull('parent_run_id')
            ->unless($request->boolean('include_tests'), fn (Builder $query) => $query->where('trigger_type', '!=', NodeTester::TRIGGER_TYPE))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['workspace'] ?? null, fn (Builder $query, string $workspace) => $query->where('workspace_id', $workspace))
            ->when($filters['trigger_type'] ?? null, fn (Builder $query, string $type) => $query->where('trigger_type', $type))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->with(['workspace' => fn ($query) => $query->withTrashed()->select('id', 'name'), 'workflow' => fn ($query) => $query->withTrashed()->select('id', 'name')])
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.runs.index', [
            'runs' => $runs,
            'filters' => $filters,
        ]);
    }

    public function show(Run $run): View
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

        return view('admin.runs.show', ['run' => $run]);
    }
}
