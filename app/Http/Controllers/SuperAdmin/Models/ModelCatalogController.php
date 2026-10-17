<?php

namespace App\Http\Controllers\SuperAdmin\Models;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Models\ModelCatalogRequest;
use App\Models\Ai\ModelCatalog;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\SuperAdmin\SuperAdminModelCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ModelCatalogController extends Controller
{
    public function __construct(private readonly SuperAdminModelCatalog $catalog) {}

    public function index(): View
    {
        return view('super-admin.models.index', [
            'entries' => ModelCatalog::query()
                ->withCount(['routes', 'routes as enabled_routes_count' => fn ($query) => $query->where('is_enabled', true), 'agents'])
                ->orderBy('sort_order')
                ->orderBy('display_name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('super-admin.models.form', ['entry' => new ModelCatalog(['is_active' => true]), 'slugs' => $this->slugs()]);
    }

    public function store(ModelCatalogRequest $request): RedirectResponse
    {
        $entry = $this->catalog->save(null, $request->validated(), $request->user('super_admin'));

        return redirect()->route('super-admin.models.show', $entry)->with('status', 'Model added. Give it at least one route so it can run.');
    }

    public function show(ModelCatalog $model): View
    {
        $model->load('routes')->loadCount('agents');

        return view('super-admin.models.show', [
            'entry' => $model,
            'providers' => collect(array_keys((array) config('ai.providers')))
                ->mapWithKeys(fn (string $provider): array => [$provider => ModelCatalogResolver::providerIsConfigured($provider)])
                ->all(),
        ]);
    }

    public function edit(ModelCatalog $model): View
    {
        return view('super-admin.models.form', ['entry' => $model, 'slugs' => $this->slugs($model->slug)]);
    }

    public function update(ModelCatalogRequest $request, ModelCatalog $model): RedirectResponse
    {
        $this->catalog->save($model, $request->validated(), $request->user('super_admin'));

        return redirect()->route('super-admin.models.show', $model)->with('status', 'Model saved.');
    }

    /**
     * @return array<int, string>
     */
    private function slugs(?string $except = null): array
    {
        return ModelCatalog::query()->when($except, fn ($query) => $query->where('slug', '!=', $except))->orderBy('slug')->pluck('slug')->all();
    }
}
