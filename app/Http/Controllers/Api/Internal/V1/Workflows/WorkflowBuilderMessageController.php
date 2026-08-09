<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Actions\Workflows\Builder\SendWorkflowBuilderMessageAction;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\StoreWorkflowBuilderMessageRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowBuilderMessageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workspaces\Workspace;

class WorkflowBuilderMessageController extends Controller
{
    public function __construct(private readonly SendWorkflowBuilderMessageAction $sendMessage) {}

    public function store(StoreWorkflowBuilderMessageRequest $request, Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);

        $reply = $this->sendMessage->execute($session, $request->validated('message'));

        return ApiResponse::success(['message' => WorkflowBuilderMessageResource::make($reply)]);
    }
}
