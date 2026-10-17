<?php

namespace App\Http\Controllers\SuperAdmin\Templates;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Templates\AddCollectionItemRequest;
use App\Http\Requests\SuperAdmin\Templates\CollectionRequest;
use App\Models\Templates\AgentTemplate;
use App\Models\Templates\TemplateCollection;
use App\Models\Templates\TemplateCollectionItem;
use App\Models\Templates\WorkflowTemplate;
use App\Services\SuperAdmin\SuperAdminTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gallery collections ("packs"). Route model binding only resolves gallery
 * rows, so a workspace's own collection 404s here.
 */
class CollectionController extends Controller
{
    public function __construct(private readonly SuperAdminTemplates $templates) {}

    public function index(): View
    {
        return view('super-admin.templates.collections.index', [
            'collections' => TemplateCollection::query()->whereNull('workspace_id')->withCount('items')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('super-admin.templates.collections.form', ['collection' => new TemplateCollection]);
    }

    public function store(CollectionRequest $request): RedirectResponse
    {
        $collection = $this->templates->saveCollection(null, $request->validated(), $request->user('super_admin'));

        return redirect()->route('super-admin.templates.collections.show', $collection)->with('status', 'Collection created as a hidden draft. Add templates, then publish.');
    }

    public function show(string $collection): View
    {
        $collection = $this->gallery($collection)->load('items.templatable');

        return view('super-admin.templates.collections.show', [
            'collection' => $collection,
            'available' => [
                'workflow' => WorkflowTemplate::query()->whereNull('workspace_id')->orderBy('name')->get(['id', 'name', 'visibility']),
                'agent' => AgentTemplate::query()->whereNull('workspace_id')->orderBy('name')->get(['id', 'name', 'visibility']),
            ],
        ]);
    }

    public function edit(string $collection): View
    {
        return view('super-admin.templates.collections.form', ['collection' => $this->gallery($collection)]);
    }

    public function update(CollectionRequest $request, string $collection): RedirectResponse
    {
        $collection = $this->templates->saveCollection($this->gallery($collection), $request->validated(), $request->user('super_admin'));

        return redirect()->route('super-admin.templates.collections.show', $collection)->with('status', 'Collection saved.');
    }

    public function publish(Request $request, string $collection): RedirectResponse
    {
        $published = $request->boolean('published');

        $this->templates->setPublished($this->gallery($collection), $published, $request->user('super_admin'));

        return back()->with('status', $published ? 'Collection published.' : 'Collection hidden.');
    }

    public function destroy(Request $request, string $collection): RedirectResponse
    {
        $this->templates->deleteCollection($this->gallery($collection), $request->user('super_admin'));

        return redirect()->route('super-admin.templates.collections.index')->with('status', 'Collection deleted.');
    }

    public function addItem(AddCollectionItemRequest $request, string $collection): RedirectResponse
    {
        [$kind, $templateId] = $request->template();

        $this->templates->addToCollection($this->gallery($collection), $kind, $templateId, $request->user('super_admin'));

        return back()->with('status', 'Template added.');
    }

    public function removeItem(Request $request, string $collection, TemplateCollectionItem $item): RedirectResponse
    {
        $this->templates->removeFromCollection($this->gallery($collection), $item, $request->user('super_admin'));

        return back()->with('status', 'Template removed from the collection.');
    }

    public function moveItem(Request $request, string $collection, TemplateCollectionItem $item): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $this->templates->moveInCollection($this->gallery($collection), $item, $direction, $request->user('super_admin'));

        return back();
    }

    private function gallery(string $id): TemplateCollection
    {
        return TemplateCollection::query()->whereNull('workspace_id')->findOrFail($id);
    }
}
