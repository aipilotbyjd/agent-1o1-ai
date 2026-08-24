<?php

namespace App\Http\Controllers\Api\Internal\V1\Nodes;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Nodes\NodeCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Nodes\NodeCategory;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Http\Request;

class NodeCategoryController extends Controller
{
    public function __construct(private readonly NodeRegistry $registry) {}

    /**
     * `nodes_count` counts built-in nodes only, same as `show()`. Pass
     * `?include_nodes=1` to also get each category's nodes inline.
     */
    public function index(Request $request)
    {
        $includeNodes = $request->boolean('include_nodes');
        $catalog = collect($this->registry->catalog())->groupBy('category');

        $categories = NodeCategory::query()->orderBy('sort_order')->get()
            ->map(function (NodeCategory $category) use ($catalog, $includeNodes): array {
                $nodes = $catalog->get($category->slug, collect())->values();

                return [
                    ...NodeCategoryResource::make($category)->resolve(),
                    'nodes_count' => $nodes->count(),
                    ...($includeNodes ? ['nodes' => $nodes] : []),
                ];
            });

        return ApiResponse::success(['categories' => $categories]);
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
