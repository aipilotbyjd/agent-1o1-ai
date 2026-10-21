<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\IndexAuditLogRequest;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\SuperAdmin\SuperAdminAuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    private const int PER_PAGE = 50;

    public function index(IndexAuditLogRequest $request): View
    {
        $filters = $request->validated();

        $entries = $this->filtered($request)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('super-admin.audit-log.index', [
            'entries' => $entries,
            'filters' => $filters,
            'superAdmins' => SuperAdmin::query()->orderBy('email')->get(['id', 'email']),
        ]);
    }

    /**
     * The filtered log as a CSV download, streamed so a large range never
     * sits in memory. The export itself is recorded.
     */
    public function export(IndexAuditLogRequest $request, SuperAdminAuditLogger $audit): StreamedResponse
    {
        $audit->record($request->user('super_admin'), 'audit_log.exported', null, null, $request->validated());

        $query = $this->filtered($request);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['created_at', 'super_admin', 'action', 'subject_type', 'subject_id', 'ip_address', 'before', 'after']);

            foreach ($query->lazy(500) as $entry) {
                fputcsv($out, array_map($this->csvSafe(...), [
                    $entry->created_at?->toIso8601String(),
                    $entry->superAdmin?->email,
                    $entry->action,
                    $entry->subject_type,
                    $entry->subject_id,
                    $entry->ip_address,
                    $entry->before !== null ? json_encode($entry->before) : null,
                    $entry->after !== null ? json_encode($entry->after) : null,
                ]));
            }

            fclose($out);
        }, 'super-admin-audit-log-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Builder<SuperAdminAuditLog>
     */
    private function filtered(IndexAuditLogRequest $request): Builder
    {
        $filters = $request->validated();
        $actionPrefix = $request->actionPrefix();

        return SuperAdminAuditLog::query()
            ->when($actionPrefix !== null, fn (Builder $query) => $query->whereRaw('action like ? escape ?', [$actionPrefix, '\\']))
            ->when($filters['super_admin'] ?? null, fn (Builder $query, string $superAdmin) => $query->where('super_admin_id', $superAdmin))
            ->when($filters['subject'] ?? null, fn (Builder $query, string $subject) => $query->where('subject_id', $subject))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->with(['superAdmin:id,email'])
            ->latest('created_at');
    }

    /**
     * Stops a spreadsheet from running a cell that starts like a formula.
     */
    private function csvSafe(?string $value): ?string
    {
        return $value !== null && $value !== '' && str_contains('=+-@', $value[0]) ? "'".$value : $value;
    }
}
