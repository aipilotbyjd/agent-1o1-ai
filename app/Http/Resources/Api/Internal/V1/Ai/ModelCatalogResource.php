<?php

namespace App\Http\Resources\Api\Internal\V1\Ai;

use App\Models\Ai\ModelCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ModelCatalog
 *
 * `id` is included because `Agent.model_catalog_id` is what
 * `StoreAgentRequest`/`UpdateAgentRequest` actually validate against
 * (`exists:model_catalog,id`) — a picker built on this response needs it to
 * submit a selection. Deliberately excludes `routes` — which real
 * backend(s), credentials, and priorities serve this entry is never exposed
 * to agent/workflow-facing API responses. See `Services\Ai\ModelCatalogResolver`.
 */
class ModelCatalogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'display_name' => $this->display_name,
            'brand' => $this->brand,
            'capabilities' => $this->capabilities,
        ];
    }
}
