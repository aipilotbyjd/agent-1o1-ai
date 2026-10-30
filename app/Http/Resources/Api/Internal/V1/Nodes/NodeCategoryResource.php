<?php

namespace App\Http\Resources\Api\Internal\V1\Nodes;

use App\Enums\NodeCategoryFunction;
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
        $functions = $this->kind === 'app'
            ? ($this->functions?->isNotEmpty() ? $this->functions : collect([NodeCategoryFunction::Other]))
            : collect();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'kind' => $this->kind,
            'is_pinned' => (bool) ($this->resource->getAttributes()['is_pinned'] ?? false),
            'function' => $functions->first()?->value,
            'functions' => $functions->map(fn (NodeCategoryFunction $function): array => [
                'slug' => $function->value,
                'label' => $function->label(),
                'order' => $function->sortOrder(),
            ])->values()->all(),
            'vendor' => $this->vendor === null ? null : ['slug' => $this->vendor->value, 'label' => $this->vendor->label()],
        ];
    }
}
