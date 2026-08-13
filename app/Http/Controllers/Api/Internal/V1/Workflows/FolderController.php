<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\MoveWorkflowsRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\StoreFolderRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\UpdateFolderRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\FolderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Folder;
use App\Models\Workspaces\Workspace;

class FolderController extends Controller
{
    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowView);

        $folders = $workspace->folders()
            ->whereNull('parent_id')
            ->with('children')
            ->withCount('workflows')
            ->orderBy('position')
            ->get();

        return ApiResponse::success(['folders' => FolderResource::collection($folders)]);
    }

    public function store(StoreFolderRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowManage);

        $folder = $workspace->folders()->create($request->validated());

        return ApiResponse::created(['folder' => FolderResource::make($folder)], 'Folder created successfully.');
    }

    public function update(UpdateFolderRequest $request, Workspace $workspace, Folder $folder)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $folder);

        $folder->update($request->validated());

        return ApiResponse::success(['folder' => FolderResource::make($folder)], 'Folder updated successfully.');
    }

    public function destroy(Workspace $workspace, Folder $folder)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $folder);

        // Workflows and child folders fall back to no folder (FK is nullOnDelete).
        $folder->delete();

        return ApiResponse::noContent();
    }

    public function moveWorkflows(MoveWorkflowsRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowManage);

        $workspace->workflows()
            ->whereIn('id', $request->validated('workflow_ids'))
            ->update(['folder_id' => $request->validated('folder_id')]);

        return ApiResponse::success(message: 'Workflows moved.');
    }
}
