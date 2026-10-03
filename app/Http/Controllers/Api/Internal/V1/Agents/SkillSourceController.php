<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Agents\SkillSourceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\SkillSource;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\Skills\SkillSources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * GitHub repositories the workspace's skills are synced from. Picking an
 * account and a repository reuses the knowledge base's `sources/apps` and
 * `sources/options?type=github`.
 */
class SkillSourceController extends Controller
{
    public function __construct(private readonly SkillSources $sources) {}

    public function index(Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AgentView);

        return ApiResponse::success([
            'sources' => SkillSourceResource::collection($workspace->skillSources()->with('credential:id,name')->latest()->get()),
        ]);
    }

    /**
     * The skills a repository holds, read without connecting it.
     */
    public function preview(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);

        try {
            $preview = $this->sources->preview($workspace, $request->user(), $request->only(['repo', 'branch', 'path', 'credential_id']));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(['preview' => $preview]);
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);

        $source = $this->sources->create($workspace, $request->user(), $request->only(['repo', 'branch', 'path', 'credential_id', 'is_shared']));

        return ApiResponse::created(['source' => SkillSourceResource::make($source->load('credential:id,name'))], 'Repository connected. Syncing now.');
    }

    public function sync(Workspace $workspace, SkillSource $skillSource): JsonResponse
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skillSource);

        $this->sources->queueSync($skillSource, force: true);

        return ApiResponse::success(['source' => SkillSourceResource::make($skillSource->refresh()->load('credential:id,name'))], 'Syncing.');
    }

    /**
     * `keep_skills` keeps the synced skills as ordinary, editable ones;
     * without it they're removed along with the source.
     */
    public function destroy(Request $request, Workspace $workspace, SkillSource $skillSource): Response
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skillSource);

        $this->sources->disconnect($skillSource, $request->boolean('keep_skills'));

        return ApiResponse::noContent();
    }
}
