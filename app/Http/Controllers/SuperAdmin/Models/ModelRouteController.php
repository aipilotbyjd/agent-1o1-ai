<?php

namespace App\Http\Controllers\SuperAdmin\Models;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Models\ModelRouteRequest;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Services\SuperAdmin\SuperAdminModelCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ModelRouteController extends Controller
{
    public function __construct(private readonly SuperAdminModelCatalog $catalog) {}

    public function store(ModelRouteRequest $request, ModelCatalog $model): RedirectResponse
    {
        $this->catalog->addRoute($model, [
            'execution_provider' => $request->string('execution_provider')->toString(),
            'execution_model_id' => $request->string('execution_model_id')->toString(),
            'priority' => $request->integer('priority'),
            'is_enabled' => $request->boolean('is_enabled'),
        ], $request->user('super_admin'));

        return back()->with('status', 'Route saved.');
    }

    public function update(ModelRouteRequest $request, ModelCatalog $model, ModelRoute $route): RedirectResponse
    {
        abort_unless($route->model_catalog_id === $model->id, 404);

        $this->catalog->updateRoute($route, ['priority' => $request->integer('priority'), 'is_enabled' => $request->boolean('is_enabled')], $request->user('super_admin'));

        return back()->with('status', 'Route saved.');
    }

    public function resetFailures(Request $request, ModelCatalog $model, ModelRoute $route): RedirectResponse
    {
        abort_unless($route->model_catalog_id === $model->id, 404);

        $this->catalog->resetFailures($route, $request->user('super_admin'));

        return back()->with('status', 'Failure count reset.');
    }

    public function destroy(Request $request, ModelCatalog $model, ModelRoute $route): RedirectResponse
    {
        abort_unless($route->model_catalog_id === $model->id, 404);

        $this->catalog->deleteRoute($route, $request->user('super_admin'));

        return back()->with('status', 'Route deleted.');
    }
}
