<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The workspace's published workflows — what a `subflow` or `loop` node's
 * `workflow_id` can point at. Only published ones: the engine refuses to
 * start an unpublished child. Loop Mode's hidden internal children are left
 * out, and so is the workflow this session is building (it can't call
 * itself).
 */
class ListWorkflowsTool implements Tool
{
    private const int LIMIT = 50;

    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'list_workflows';
    }

    public function description(): Stringable|string
    {
        return "List this workspace's published workflows (id, name, description) — the workflow_id a subflow or loop node runs as its child workflow. Pass 'search' to filter by name.";
    }

    public function handle(Request $request): Stringable|string
    {
        $search = trim((string) ($request->all()['search'] ?? ''));

        $workflows = $this->session->workspace->workflows()
            ->visible()
            ->whereNotNull('current_version_id')
            ->when($this->session->workflow_id !== null, fn ($query) => $query->whereKeyNot($this->session->workflow_id))
            ->when($search !== '', fn ($query) => $query->whereLike('name', "%{$search}%"))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'description', 'input_schema']);

        if ($workflows->isEmpty()) {
            return $search === ''
                ? 'This workspace has no published workflows yet, so there is nothing a subflow or loop node can run. For repeating one node per item, use that node\'s Loop Mode (_loop) instead.'
                : "No published workflow matches [{$search}].";
        }

        return json_encode($workflows->map(fn ($workflow) => array_filter([
            'id' => $workflow->id,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'input_schema' => $workflow->input_schema,
        ], fn ($value) => $value !== null))->all(), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Optional text to match against workflow names.'),
        ];
    }
}
