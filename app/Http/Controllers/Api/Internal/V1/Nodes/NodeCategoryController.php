<?php

namespace App\Http\Controllers\Api\Internal\V1\Nodes;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Nodes\NodeCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Nodes\NodeCategory;
use App\Services\Workflows\NodeRegistry;

class NodeCategoryController extends Controller
{
    public function __construct(private readonly NodeRegistry $registry) {}

    public function index()
    {
        $categories = NodeCategory::query()->orderBy('sort_order')->get();

        return ApiResponse::success(['categories' => NodeCategoryResource::collection($categories)]);
    }

    /**
     * A single category plus the built-in nodes filed under it. Custom nodes
     * aren't included here — this route isn't workspace-scoped, see
     * `GET /workspaces/{workspace}/nodes?category=...` for those.
     */
    public function show(NodeCategory $nodeCategory)
    {
        $nodes = collect($this->registry->catalog())
            ->where('category', $nodeCategory->slug)
            ->values();

        return ApiResponse::success([
            'category' => NodeCategoryResource::make($nodeCategory),
            'nodes' => $nodes,
            'nodes_count' => $nodes->count(),
        ]);
    }
}
