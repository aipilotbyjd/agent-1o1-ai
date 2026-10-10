<?php

namespace App\Http\Controllers\Api\Internal\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Ai\ModelCatalogResource;
use App\Http\Responses\ApiResponse;
use App\Models\Ai\ModelCatalog;
use App\Services\Ai\ByokProviderRegistrar;
use Illuminate\Http\Request;

/**
 * The public model picker for agents/workflow "Ask AI" nodes — not
 * workspace-scoped, same as `Nodes\NodeController::globalCatalog()`. Which
 * real backend(s) actually serve an entry is never exposed here; see
 * `ModelCatalogResource` and `Services\Ai\ModelCatalogResolver`.
 *
 * `?workspace_id=` (one the caller belongs to) folds that workspace's own
 * provider keys into each entry's `is_available`.
 */
class ModelCatalogController extends Controller
{
    public function index(Request $request, ByokProviderRegistrar $byok)
    {
        $workspaceId = $request->query('workspace_id');
        $ownKeyProviders = is_string($workspaceId) && $request->user()->workspaces()->whereKey($workspaceId)->exists()
            ? $byok->coveredProviders($workspaceId, $request->user()->id)
            : [];

        $catalog = ModelCatalog::query()
            ->with(['routes' => fn ($query) => $query->where('is_enabled', true)])
            ->where('is_active', true)
            ->where('is_internal', false)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get();

        return ApiResponse::success([
            'model_catalog' => $catalog->map(fn (ModelCatalog $entry) => ModelCatalogResource::make($entry)->withOwnKeysFor($ownKeyProviders)),
        ]);
    }
}
