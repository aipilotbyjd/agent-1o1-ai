<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Agents\AgentActionStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Agents\AgentActionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentSession;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * An agent's action log — everything it did or tried to do that changes
 * something, newest first — and one conversation's slice of it, which is
 * what a chat renders its approval cards from. A subagent's actions are
 * included in its parent conversation's list, since that's where a person
 * is watching.
 */
class AgentActionController extends Controller
{
    public function index(Request $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::paginated(AgentActionResource::collection(
            $this->filtered($request, $agent->actions()->getQuery())->with('session')->latest('id')->paginate(50),
        ));
    }

    public function sessionIndex(Request $request, Workspace $workspace, Agent $agent, AgentSession $session)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        $query = AgentAction::query()->where(fn (Builder $query) => $query
            ->where('agent_session_id', $session->id)
            ->orWhereIn('agent_session_id', AgentSession::query()->where('parent_session_id', $session->id)->select('id')));

        return ApiResponse::success([
            'actions' => AgentActionResource::collection($this->filtered($request, $query)->with(['agent', 'session'])->oldest('id')->get()),
        ]);
    }

    /**
     * @param  Builder<AgentAction>  $query
     * @return Builder<AgentAction>
     */
    private function filtered(Request $request, Builder $query): Builder
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(AgentActionStatus::class)],
            'tool_name' => ['nullable', 'string', 'max:255'],
        ]);

        return $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['tool_name'] ?? null, fn (Builder $query, string $tool) => $query->where('tool_name', $tool));
    }
}
