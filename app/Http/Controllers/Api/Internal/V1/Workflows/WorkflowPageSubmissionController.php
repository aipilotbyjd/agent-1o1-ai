<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\SubmitWorkflowPageRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowPageSubmissionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workflows\WorkflowPageSubmission;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\Pages\PageSubmissions;

/**
 * Members using a page from inside the app, and its run history. A test run
 * (`test: true`) comes from the builder's preview: it may use an unpublished
 * page and doesn't count toward the page's daily limit, so it needs
 * `page.manage`.
 */
class WorkflowPageSubmissionController extends Controller
{
    private const int HISTORY_LIMIT = 50;

    public function __construct(private readonly PageSubmissions $submissions) {}

    public function index(Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageView);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $history = $page->submissions()
            ->with(['user:id,name', 'run:id,status,started_at,finished_at'])
            ->latest()
            ->limit(self::HISTORY_LIMIT)
            ->get();

        return ApiResponse::success(['submissions' => WorkflowPageSubmissionResource::collection($history)]);
    }

    public function store(SubmitWorkflowPageRequest $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $test = $request->boolean('test');

        if ($test) {
            $this->requirePermission(Permission::PageManage);
        } else {
            abort_if(! $page->isPublished(), 409, 'Publish this page before using it.');
            abort_if(! $page->is_enabled, 409, 'This page is turned off.');
        }

        [$submission] = $this->submissions->submit($page, $request->validated('input') ?? [], $request->user(), test: $test);

        return ApiResponse::success([
            'submission' => $this->submissions->result($submission, $this->contentFor($page, $submission), forMember: true),
        ], 'Run started.', 202);
    }

    public function show(Workspace $workspace, WorkflowPage $page, WorkflowPageSubmission $submission)
    {
        $this->requirePermission(Permission::PageView);
        $this->ensureBelongsToWorkspace($workspace, $page);
        abort_if($submission->workflow_page_id !== $page->id, 404);

        return ApiResponse::success([
            'submission' => $this->submissions->result($submission->load('run'), $this->contentFor($page, $submission), forMember: true),
        ]);
    }

    /**
     * @return array{blocks: array<int, array<string, mixed>>, theme: array<string, mixed>}
     */
    private function contentFor(WorkflowPage $page, WorkflowPageSubmission $submission): array
    {
        return $submission->is_test ? $page->content : ($page->published_content ?? $page->content);
    }
}
