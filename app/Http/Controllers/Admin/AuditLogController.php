<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAuditLogRequest;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminAuditLog;
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

        $entries = AdminAuditLog::query()
            ->when($actionPrefix !== null, fn (Builder $query) => $query->where('action', 'like', $actionPrefix))
            ->when(($filters['actor'] ?? null) === 'panel', fn (Builder $query) => $query->whereNotNull('admin_id'))
            ->when(($filters['actor'] ?? null) === 'platform', fn (Builder $query) => $query->whereNotNull('admin_user_id'))
            ->when(($filters['actor'] ?? null) === 'system', fn (Builder $query) => $query->whereNull('admin_id')->whereNull('admin_user_id'))
            ->when($filters['admin'] ?? null, fn (Builder $query, string $admin) => $query->where('admin_id', $admin))
            ->when($filters['subject'] ?? null, fn (Builder $query, string $subject) => $query->where('subject_id', $subject))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->with(['admin:id,email', 'panelAdmin:id,email'])
            ->latest('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.audit-log.index', [
            'entries' => $entries,
            'filters' => $filters,
            'admins' => Admin::query()->orderBy('email')->get(['id', 'email']),
        ]);
    }
}
