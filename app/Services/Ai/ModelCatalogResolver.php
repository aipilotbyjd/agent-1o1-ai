<?php

namespace App\Services\Ai;

use App\Models\Agents\Agent;
use App\Models\Ai\ModelCatalog;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Turns a public `ModelCatalog` slug into the ordered `provider => model`
 * array `laravel/ai` accepts as its `provider:` argument for automatic
 * failover (see the SDK's "Failover" docs) — the one place a catalog
 * selection becomes a real execution backend. Callers (`AgentRunner`,
 * `Nodes\AiAutomation\AskAiNode`) never see individual `ModelRoute` rows,
 * only this resolved chain, so the SDK's own retry/backoff handles a
 * transient failure on the primary backend without any custom router.
 */
class ModelCatalogResolver
{
    private const int CACHE_TTL_SECONDS = 300;

    /**
     * The provider/model to prompt `$agent` with: its `provider`/`model`
     * columns, or its catalog entry's failover chain (which carries its own
     * model ids, so `model` is null). Anything prompting on an agent's behalf
     * should use this rather than reading those columns directly.
     *
     * @return array{0: string|array<string, string>, 1: ?string}
     */
    public function forAgent(Agent $agent): array
    {
        if ($agent->model_catalog_id === null) {
            return [$agent->provider, $agent->model];
        }

        return [$this->providerChain($agent->modelCatalog->slug), null];
    }

    /**
     * @return array<string, string> provider (a `Lab` value or custom
     *                               `openai-compatible` config key) => model id, ordered by priority
     */
    public function providerChain(string $catalogSlug): array
    {
        $chain = Cache::remember(
            $this->cacheKey($catalogSlug),
            self::CACHE_TTL_SECONDS,
            fn () => $this->resolve($catalogSlug),
        );

        if ($chain === []) {
            throw new RuntimeException("Model catalog entry [{$catalogSlug}] has no enabled execution route.");
        }

        return $chain;
    }

    /**
     * Invalidates the cached chain for a catalog entry — call after
     * changing its routes (enabling/disabling, reordering, adding a
     * backend) so the next resolution reflects the change immediately
     * rather than waiting out the cache TTL.
     */
    public function forget(string $catalogSlug): void
    {
        Cache::forget($this->cacheKey($catalogSlug));
    }

    /**
     * @return array<string, string>
     */
    private function resolve(string $catalogSlug): array
    {
        $catalog = ModelCatalog::query()
            ->where('slug', $catalogSlug)
            ->where('is_active', true)
            ->first();

        if ($catalog === null) {
            return [];
        }

        return $catalog->routes()
            ->where('is_enabled', true)
            ->orderBy('priority')
            ->pluck('execution_model_id', 'execution_provider')
            ->all();
    }

    private function cacheKey(string $catalogSlug): string
    {
        return "model-catalog-chain:{$catalogSlug}";
    }
}
