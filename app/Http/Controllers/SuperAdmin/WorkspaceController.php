<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\IndexWorkspacesRequest;
use App\Models\Workspaces\Workspace;
use App\Services\SuperAdmin\SuperAdminWorkspaceDirectory;
use Illuminate\View\View;

/**
 * Read-only view of workspaces: plan, credits, members and recent runs.
 */
class WorkspaceController extends Controller
{
    public function __construct(private readonly SuperAdminWorkspaceDirectory $workspaces) {}

    public function index(IndexWorkspacesRequest $request): View
    {
        return view('super-admin.workspaces.index', [
            'workspaces' => $this->workspaces->search($request->validated(), $request->searchPattern()),
            'filters' => $request->validated(),
        ]);
    }

    public function show(Workspace $workspace): View
    {
        return view('super-admin.workspaces.show', $this->workspaces->detail($workspace));
    }
}
