<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\IndexAuditLogRequest;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    private const int PER_PAGE = 50;

    public function index(IndexAuditLogRequest $request): View
    {
        $filters = $request->validated();
        $actionPrefix = $request->actionPrefix();

        $entries = SuperAdminAuditLog::query()
            ->when($actionPrefix !== null, fn (Builder $query) => $query->where('action', 'like', $actionPrefix))
            ->when($filters['super_admin'] ?? null, fn (Builder $query, string $superAdmin) => $query->where('super_admin_id', $superAdmin))
            ->when($filters['subject'] ?? null, fn (Builder $query, string $subject) => $query->where('subject_id', $subject))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->with(['superAdmin:id,email'])
            ->latest('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('super-admin.audit-log.index', [
            'entries' => $entries,
            'filters' => $filters,
            'superAdmins' => SuperAdmin::query()->orderBy('email')->get(['id', 'email']),
        ]);
    }
}
