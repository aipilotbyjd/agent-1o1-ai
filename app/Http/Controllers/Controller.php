<?php

namespace App\Http\Controllers;

use App\Enums\Workspaces\Permission;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    protected function requirePermission(Permission $permission): void
    {
        $this->authorize($permission->value);
    }

    protected function ensureBelongsToWorkspace(Workspace $workspace, Model $model): void
    {
        abort_if($model->workspace_id !== $workspace->id, 404);
    }
}
