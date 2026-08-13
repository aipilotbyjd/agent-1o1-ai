<?php

namespace App\Http\Resources\Api\Internal\V1\Nodes;

use App\Models\Nodes\NodeCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NodeCategory
 */
class NodeCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'kind' => $this->kind,
        ];
    }
}
