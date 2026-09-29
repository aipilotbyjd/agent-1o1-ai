<?php

namespace App\Http\Controllers\Api\Internal\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Admin\AdminAuditLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\Admin\AdminAuditLog;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AdminAuditLog::query()
            ->with('admin:id,name,email')
            ->when($request->filled('action'), fn ($query) => $query->where('action', 'like', $request->string('action').'%'))
            ->when($request->filled('subject_type'), fn ($query) => $query->where('subject_type', $request->string('subject_type')))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->string('subject_id')))
            ->latest('created_at')
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::paginated(AdminAuditLogResource::collection($logs));
    }
}
