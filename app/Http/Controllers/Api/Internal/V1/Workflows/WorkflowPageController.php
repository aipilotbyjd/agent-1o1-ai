<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\StoreWorkflowPageRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\UpdateWorkflowPageRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowPageListResource;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowPageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\Pages\PagePresenter;
use App\Services\Workflows\Pages\WorkflowPages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Workflow pages — see `WorkflowPage`. Members with `page.view` can open and
 * use a published page from inside the app; building, publishing and sharing
 * one takes `page.manage`.
 */
class WorkflowPageController extends Controller
{
    public function __construct(
        private readonly WorkflowPages $pages,
        private readonly PagePresenter $presenter,
    ) {}

    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::PageView);

        $pages = $workspace->workflowPages()
            ->with(['workflow' => fn ($query) => $query->withTrashed()])
            ->withCount(['submissions as runs_count' => fn (Builder $query) => $query->where('is_test', false)])
            ->withMax('submissions', 'created_at')
            ->when($request->query('workflow_id'), fn (Builder $query, string $workflowId) => $query->where('workflow_id', $workflowId))
            ->latest('updated_at')
            ->get();

        return ApiResponse::success(['pages' => WorkflowPageListResource::collection($pages)]);
    }

    public function store(StoreWorkflowPageRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::PageManage);

        $workflow = $this->workflowIn($workspace, $request->validated('workflow_id'));

        $page = $this->pages->create($workflow, $request->user(), $request->validated('name'), $request->validated('description'));

        return ApiResponse::created(['page' => $this->full($page)], 'Page created.');
    }

    public function show(Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageView);
        $this->ensureBelongsToWorkspace($workspace, $page);

        return ApiResponse::success(['page' => $this->full($page)]);
    }

    public function update(UpdateWorkflowPageRequest $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $changes = $request->validated();

        if (array_key_exists('workflow_id', $changes)) {
            $this->workflowIn($workspace, $changes['workflow_id']);
        }

        $page = $this->pages->update($page, $changes);

        return ApiResponse::success(['page' => $this->full($page)], 'Page saved.');
    }

    public function destroy(Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $this->pages->delete($page);

        return ApiResponse::success(null, 'Page deleted.');
    }

    public function duplicate(Request $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        return ApiResponse::created(['page' => $this->full($this->pages->duplicate($page, $request->user()))], 'Page duplicated.');
    }

    public function publish(Request $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $page = $this->pages->publish($page, $request->user());

        return ApiResponse::success(['page' => $this->full($page)], 'Page published.');
    }

    public function unpublish(Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        return ApiResponse::success(['page' => $this->full($this->pages->unpublish($page))], 'Page unpublished.');
    }

    public function rotateLink(Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        return ApiResponse::success(['page' => $this->full($this->pages->rotateLink($page))], 'New link created. The old one no longer works.');
    }

    /**
     * The page as a member using it sees it — the published page, or with
     * `?draft=1` the draft, for someone who can edit it.
     */
    public function view(Request $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageView);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $draft = $request->boolean('draft');

        if ($draft) {
            $this->requirePermission(Permission::PageManage);
        }

        $content = $draft ? $page->content : $page->published_content;

        abort_if($content === null, 404, 'This page has not been published yet.');
        abort_if(! $draft && ! $page->is_enabled, 404, 'This page is turned off.');

        return ApiResponse::success(['view' => $this->presenter->present($page, $content)]);
    }

    private function workflowIn(Workspace $workspace, string $workflowId): Workflow
    {
        return $workspace->workflows()->visible()->findOrFail($workflowId);
    }

    private function full(WorkflowPage $page): WorkflowPageResource
    {
        return WorkflowPageResource::make($page->fresh()->load([
            'workflow' => fn ($query) => $query->withTrashed(),
            'publisher:id,name',
            'creator:id,name',
        ]));
    }
}
