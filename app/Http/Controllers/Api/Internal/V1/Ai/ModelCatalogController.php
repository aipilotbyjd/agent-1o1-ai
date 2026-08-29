<?php

namespace App\Http\Controllers\Api\Internal\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Ai\ModelCatalogResource;
use App\Http\Responses\ApiResponse;
use App\Models\Ai\ModelCatalog;

/**
 * The public model picker for agents/workflow "Ask AI" nodes — not
 * workspace-scoped, same as `Nodes\NodeController::globalCatalog()`. Which
 * real backend(s) actually serve an entry is never exposed here; see
 * `ModelCatalogResource` and `Services\Ai\ModelCatalogResolver`.
 */
class ModelCatalogController extends Controller
{
    public function index()
    {
        $catalog = ModelCatalog::query()
            ->where('is_active', true)
            ->where('is_internal', false)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get();

        return ApiResponse::success(['model_catalog' => ModelCatalogResource::collection($catalog)]);
    }
}
