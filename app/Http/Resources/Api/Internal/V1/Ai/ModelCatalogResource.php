<?php

namespace App\Http\Resources\Api\Internal\V1\Ai;

use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Services\Ai\ModelCatalogResolver;
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
 *
 * `is_available` counts a route the platform has no key for as usable when
 * the asking member's workspace has its own key for that provider — see
 * `withOwnKeysFor()` and `ByokProviderRegistrar::coveredProviders()`. A
 * workspace that never uses the platform's keys counts only its own.
 */
class ModelCatalogResource extends JsonResource
{
    /**
     * @var array<int, string>
     */
    private array $ownKeyProviders = [];

    private bool $platformKeysAllowed = true;

    /**
     * @param  array<int, string>  $providers
     */
    public function withOwnKeysFor(array $providers, bool $platformKeysAllowed = true): static
    {
        $this->ownKeyProviders = $providers;
        $this->platformKeysAllowed = $platformKeysAllowed;

        return $this;
    }

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
            'is_available' => $this->whenLoaded('routes', fn (): bool => $this->routes->contains(
                fn (ModelRoute $route): bool => in_array($route->execution_provider, $this->ownKeyProviders, true)
                    || ($this->platformKeysAllowed && ModelCatalogResolver::providerIsConfigured($route->execution_provider)),
            )),
        ];
    }
}
