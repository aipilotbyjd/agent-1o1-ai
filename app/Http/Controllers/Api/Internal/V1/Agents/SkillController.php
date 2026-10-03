<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\StoreSkillRequest;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateSkillRequest;
use App\Http\Resources\Api\Internal\V1\Agents\SkillResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Skill;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Str;

class SkillController extends Controller
{
    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        return ApiResponse::success([
            'skills' => SkillResource::collection($workspace->skills()->latest()->get()),
        ]);
    }

    public function store(StoreSkillRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentSkillManage);

        $skill = $workspace->skills()->create([
            ...$request->validated(),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')).'-'.Str::random(6),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['skill' => SkillResource::make($skill)], 'Skill created successfully.');
    }

    public function show(Workspace $workspace, Skill $skill)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $skill);

        return ApiResponse::success(['skill' => SkillResource::make($skill)]);
    }

    public function update(UpdateSkillRequest $request, Workspace $workspace, Skill $skill)
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skill);

        // A version bump is a signal to callers that an in-flight session's
        // Skill context may have changed — bump it only when the instructions
        // actually change, not on cosmetic fields like color/icon. Set in the
        // same save as the edit; `version` isn't fillable.
        $skill->fill($request->validated());

        if ($skill->isDirty('instructions')) {
            $skill->version++;
        }

        $skill->save();

        return ApiResponse::success(['skill' => SkillResource::make($skill->fresh())], 'Skill updated successfully.');
    }

    public function destroy(Workspace $workspace, Skill $skill)
    {
        $this->requirePermission(Permission::AgentSkillManage);
        $this->ensureBelongsToWorkspace($workspace, $skill);

        $skill->delete();

        return ApiResponse::noContent();
    }
}
