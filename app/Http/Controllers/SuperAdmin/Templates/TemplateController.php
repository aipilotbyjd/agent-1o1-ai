<?php

namespace App\Http\Controllers\SuperAdmin\Templates;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Templates\UpdateTemplateRequest;
use App\Services\SuperAdmin\SuperAdminTemplates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateController extends Controller
{
    private const int PER_PAGE = 30;

    public function __construct(private readonly SuperAdminTemplates $templates) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'kind' => ['nullable', 'in:'.implode(',', array_keys(SuperAdminTemplates::KINDS))],
            'scope' => ['nullable', 'in:gallery,workspaces'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $kind = $filters['kind'] ?? 'workflow';
        $scope = $filters['scope'] ?? 'gallery';
        $term = trim((string) ($filters['q'] ?? ''));

        $templates = $this->templates->modelFor($kind)::query()
            ->when($scope === 'gallery', fn (Builder $query) => $query->whereNull('workspace_id'))
            ->when($scope === 'workspaces', fn (Builder $query) => $query->whereNotNull('workspace_id')->with('workspace:id,name'))
            ->when($term !== '', fn (Builder $query) => $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('super-admin.templates.index', [
            'templates' => $templates,
            'kind' => $kind,
            'scope' => $scope,
            'filters' => $filters,
        ]);
    }

    public function edit(string $kind, string $template): View
    {
        return view('super-admin.templates.edit', [
            'template' => $this->templates->findGallery($kind, $template),
            'kind' => $kind,
        ]);
    }

    public function update(UpdateTemplateRequest $request, string $kind, string $template): RedirectResponse
    {
        $this->templates->update($this->templates->findGallery($kind, $template), $request->validated(), $request->user('super_admin'));

        return redirect()->route('super-admin.templates.index', ['kind' => $kind])->with('status', 'Template saved.');
    }

    public function publish(Request $request, string $kind, string $template): RedirectResponse
    {
        $published = $request->boolean('published');

        $this->templates->setPublished($this->templates->findGallery($kind, $template), $published, $request->user('super_admin'));

        return back()->with('status', $published ? 'Published to the gallery.' : 'Hidden from the gallery.');
    }

    public function copyToGallery(Request $request, string $kind, string $template): RedirectResponse
    {
        $copy = $this->templates->copyToGallery($this->templates->find($kind, $template), $request->user('super_admin'));

        return redirect()->route('super-admin.templates.edit', [$kind, $copy->id])
            ->with('status', 'Copied to the gallery as a hidden draft. Review it, then publish.');
    }

    public function destroy(Request $request, string $kind, string $template): RedirectResponse
    {
        $this->templates->delete($this->templates->findGallery($kind, $template), $kind, $request->user('super_admin'));

        return redirect()->route('super-admin.templates.index', ['kind' => $kind])->with('status', 'Template removed from the gallery.');
    }
}
