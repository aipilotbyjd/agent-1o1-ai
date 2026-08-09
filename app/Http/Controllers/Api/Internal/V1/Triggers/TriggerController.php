<?php

namespace App\Http\Controllers\Api\Internal\V1\Triggers;

use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Triggers\TriggerType;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Triggers\StoreTriggerRequest;
use App\Http\Requests\Api\Internal\V1\Triggers\UpdateTriggerRequest;
use App\Http\Resources\Api\Internal\V1\Triggers\TriggerEventResource;
use App\Http\Resources\Api\Internal\V1\Triggers\TriggerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Triggers\Trigger;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerService;
use Illuminate\Http\Request;

class TriggerController extends Controller
{
    public function __construct(
        private readonly TriggerService $triggers,
    ) {}

    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::TriggerView);

        return ApiResponse::success([
            'triggers' => TriggerResource::collection($workspace->triggers()->latest()->get()),
        ]);
    }

    public function store(StoreTriggerRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::TriggerManage);

        $trigger = $this->triggers->create(
            $workspace,
            TriggerTargetType::from($request->validated('target_type')),
            (int) $request->validated('target_id'),
            TriggerType::from($request->validated('type')),
            $request->validated(),
            $request->user(),
        );

        return ApiResponse::created(['trigger' => TriggerResource::make($trigger)], 'Trigger created successfully.');
    }

    public function update(UpdateTriggerRequest $request, Workspace $workspace, Trigger $trigger)
    {
        $this->requirePermission(Permission::TriggerManage);
        $this->ensureBelongsToWorkspace($workspace, $trigger);

        $trigger = $this->triggers->update($trigger, $request->validated());

        return ApiResponse::success(['trigger' => TriggerResource::make($trigger)], 'Trigger updated successfully.');
    }

    public function destroy(Workspace $workspace, Trigger $trigger)
    {
        $this->requirePermission(Permission::TriggerManage);
        $this->ensureBelongsToWorkspace($workspace, $trigger);

        $trigger->delete();

        return ApiResponse::noContent();
    }

    public function run(Workspace $workspace, Trigger $trigger)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $trigger);

        if ($this->triggers->isAlreadyRunning($trigger)) {
            return ApiResponse::error('This trigger already has a run in flight.', 409);
        }

        if (! $this->triggers->canRun($trigger)) {
            return ApiResponse::error('This trigger\'s target cannot be run right now.', 409);
        }

        $event = $this->triggers->receive($trigger, TriggerType::Manual, []);

        return ApiResponse::success(['event' => TriggerEventResource::make($event)], 'Run queued.', 202);
    }

    public function rotateToken(Workspace $workspace, Trigger $trigger)
    {
        $this->requirePermission(Permission::TriggerManage);
        $this->ensureBelongsToWorkspace($workspace, $trigger);

        $trigger = $this->triggers->rotateToken($trigger);

        return ApiResponse::success(['trigger' => TriggerResource::make($trigger)], 'Token rotated successfully.');
    }

    public function events(Request $request, Workspace $workspace, Trigger $trigger)
    {
        $this->requirePermission(Permission::TriggerView);
        $this->ensureBelongsToWorkspace($workspace, $trigger);

        $events = $trigger->events()->latest()->paginate((int) $request->integer('per_page', 25));

        return ApiResponse::paginated(TriggerEventResource::collection($events));
    }
}
