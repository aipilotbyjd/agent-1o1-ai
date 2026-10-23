<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Connectors\Connector;
use App\Models\Nodes\CustomNode;
use App\Models\Templates\AgentTemplate;
use App\Models\Templates\PageTemplate;
use App\Models\Templates\WorkflowTemplate;
use App\Services\SuperAdmin\Catalog\SuperAdminCatalogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SuperAdminCatalogController extends Controller
{
    /**
     * The template kinds, as they appear in the URL.
     *
     * @var array<string, class-string<Model>>
     */
    private const TEMPLATES = [
        'workflow' => WorkflowTemplate::class,
        'agent' => AgentTemplate::class,
        'page' => PageTemplate::class,
    ];

    public function __construct(private readonly SuperAdminCatalogService $catalog) {}

    public function connectors(Request $request)
    {
        $connectors = Connector::query()
            ->when($request->filled('q'), fn ($query) => $query->where(function ($inner) use ($request) {
                $term = '%'.$request->string('q').'%';

                $inner->where('name', 'like', $term)->orWhere('key', 'like', $term);
            }))
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success($connectors->map(fn (Connector $connector): array => $this->connector($connector))->all());
    }

    public function enableConnector(ReasonRequest $request, Connector $connector)
    {
        return $this->respondConnector($this->catalog->setConnectorActive($request->user('super-admin'), $connector, true, $request->validated('reason')), 'Connector enabled.');
    }

    public function disableConnector(ReasonRequest $request, Connector $connector)
    {
        return $this->respondConnector($this->catalog->setConnectorActive($request->user('super-admin'), $connector, false, $request->validated('reason')), 'Connector disabled.');
    }

    public function nodes(Request $request)
    {
        $nodes = CustomNode::query()
            ->with('workspace:id,name')
            ->when($request->filled('workspace_id'), fn ($query) => $query->where('workspace_id', $request->string('workspace_id')))
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 25), 100));

        return ApiResponse::success($nodes->getCollection()->map(fn (CustomNode $node): array => $this->node($node))->all());
    }

    public function enableNode(ReasonRequest $request, CustomNode $node)
    {
        return $this->respondNode($this->catalog->setCustomNodeActive($request->user('super-admin'), $node, true, $request->validated('reason')), 'Node enabled.');
    }

    public function disableNode(ReasonRequest $request, CustomNode $node)
    {
        return $this->respondNode($this->catalog->setCustomNodeActive($request->user('super-admin'), $node, false, $request->validated('reason')), 'Node disabled.');
    }

    public function templates(Request $request, string $type)
    {
        $model = $this->templateModel($type);

        $templates = $model::query()
            ->when($request->filled('visibility'), fn ($query) => $query->where('visibility', $request->string('visibility')))
            ->when($request->boolean('platform'), fn ($query) => $query->whereNull('workspace_id'))
            ->latest()
            ->limit(200)
            ->get();

        return ApiResponse::success($templates->map(fn (Model $template): array => $this->template($template, $type))->all());
    }

    public function publishTemplate(ReasonRequest $request, string $type, string $template)
    {
        return $this->respondTemplate($request, $type, $template, 'public', 'Template published.');
    }

    public function unpublishTemplate(ReasonRequest $request, string $type, string $template)
    {
        return $this->respondTemplate($request, $type, $template, 'private', 'Template unpublished.');
    }

    private function respondTemplate(ReasonRequest $request, string $type, string $id, string $visibility, string $message)
    {
        $template = $this->templateModel($type)::query()->findOrFail($id);

        $template = $this->catalog->setTemplateVisibility($request->user('super-admin'), $template, $visibility, $request->validated('reason'));

        return ApiResponse::success($this->template($template, $type), $message);
    }

    /**
     * @return class-string<Model>
     */
    private function templateModel(string $type): string
    {
        return self::TEMPLATES[$type] ?? abort(404, 'Unknown template type.');
    }

    private function respondConnector(Connector $connector, string $message)
    {
        return ApiResponse::success($this->connector($connector), $message);
    }

    private function respondNode(CustomNode $node, string $message)
    {
        return ApiResponse::success($this->node($node->loadMissing('workspace:id,name')), $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function connector(Connector $connector): array
    {
        return [
            'id' => $connector->id,
            'key' => $connector->key,
            'name' => $connector->name,
            'is_active' => $connector->is_active,
            'is_featured' => $connector->is_featured,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function node(CustomNode $node): array
    {
        return [
            'id' => $node->id,
            'type' => $node->type,
            'name' => $node->name,
            'is_active' => $node->is_active,
            'workspace' => $node->workspace === null ? null : ['id' => $node->workspace->id, 'name' => $node->workspace->name],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function template(Model $template, string $type): array
    {
        return [
            'id' => $template->id,
            'type' => $type,
            'name' => $template->name,
            'slug' => $template->slug,
            'category' => $template->category,
            'visibility' => $template->visibility,
            'workspace_id' => $template->workspace_id,
            'created_at' => $template->created_at,
        ];
    }
}
