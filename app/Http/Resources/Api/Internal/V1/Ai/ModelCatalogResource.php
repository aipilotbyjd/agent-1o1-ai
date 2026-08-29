<?php

namespace App\Http\Resources\Api\Internal\V1\Ai;

use App\Models\Ai\ModelCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ModelCatalog
 *
 * Deliberately excludes `routes` — which real backend(s), credentials, and
 * priorities serve this entry is never exposed to agent/workflow-facing API
 * responses. See `Services\Ai\ModelCatalogResolver`.
 */
class ModelCatalogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'display_name' => $this->display_name,
            'brand' => $this->brand,
            'capabilities' => $this->capabilities,
        ];
    }
}
