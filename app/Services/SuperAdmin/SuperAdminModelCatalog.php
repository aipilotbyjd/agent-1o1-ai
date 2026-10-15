<?php

namespace App\Services\SuperAdmin;

use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\Ai\ModelCatalogResolver;
use Illuminate\Support\Facades\DB;

/**
 * Edits the model catalog and its execution routes for the super admin
 * panel — the web counterpart of `model-catalog:*`. Every change drops the
 * entry's cached failover chain in `ModelCatalogResolver`, so it applies to
 * the very next prompt instead of after the cache TTL.
 */
class SuperAdminModelCatalog
{
    public function __construct(
        private readonly SuperAdminAuditLogger $audit,
        private readonly ModelCatalogResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated `ModelCatalogRequest` input
     */
    public function save(?ModelCatalog $entry, array $data, SuperAdmin $actor): ModelCatalog
    {
        return DB::transaction(function () use ($entry, $data, $actor): ModelCatalog {
            $creating = $entry === null;
            $entry ??= new ModelCatalog(['slug' => $data['slug']]);

            $entry->fill([
                'display_name' => $data['display_name'],
                'brand' => $data['brand'],
                'description' => $data['description'] ?? null,
                'strengths' => $data['strengths'] ?? [],
                'speed' => $data['speed'] ?? null,
                'cost_tier' => $data['cost_tier'] ?? null,
                'capabilities' => [
                    ...($entry->capabilities ?? []),
                    'context_window' => $data['context_window'] ?? null,
                    'vision' => (bool) ($data['vision'] ?? false),
                    'tool_use' => (bool) ($data['tool_use'] ?? false),
                ],
                'is_active' => (bool) $data['is_active'],
                'is_internal' => (bool) $data['is_internal'],
                'sort_order' => $data['sort_order'] ?? 0,
                'retires_at' => $data['retires_at'] ?? null,
                'replacement_slug' => $data['replacement_slug'] ?? null,
            ]);

            $changes = $entry->getDirty();
            $before = $creating ? null : collect($entry->getOriginal())->only(array_keys($changes))->all();

            $entry->save();
            $this->resolver->forget($entry->slug);

            if ($changes !== []) {
                $this->audit->record($actor, $creating ? 'model_catalog.created' : 'model_catalog.updated', $entry, $before, $changes);
            }

            return $entry;
        });
    }

    /**
     * @param  array{execution_provider: string, execution_model_id: string, priority: int, is_enabled: bool}  $data
     */
    public function addRoute(ModelCatalog $entry, array $data, SuperAdmin $actor): ModelRoute
    {
        return DB::transaction(function () use ($entry, $data, $actor): ModelRoute {
            $route = $entry->routes()->updateOrCreate(
                ['execution_provider' => $data['execution_provider'], 'execution_model_id' => $data['execution_model_id']],
                ['priority' => $data['priority'], 'is_enabled' => $data['is_enabled']],
            );

            $this->resolver->forget($entry->slug);
            $this->audit->record($actor, 'model_route.saved', $entry, null, collect($data)->only(['execution_provider', 'execution_model_id', 'priority', 'is_enabled'])->all());

            return $route;
        });
    }

    /**
     * @param  array{priority: int, is_enabled: bool}  $data
     */
    public function updateRoute(ModelRoute $route, array $data, SuperAdmin $actor): void
    {
        DB::transaction(function () use ($route, $data, $actor): void {
            $before = $route->only(['priority', 'is_enabled']);

            $route->update(['priority' => $data['priority'], 'is_enabled' => $data['is_enabled']]);

            $this->resolver->forget($route->modelCatalog->slug);
            $this->audit->record($actor, 'model_route.updated', $route->modelCatalog, ['route' => $this->label($route), ...$before], $route->only(['priority', 'is_enabled']));
        });
    }

    public function resetFailures(ModelRoute $route, SuperAdmin $actor): void
    {
        DB::transaction(function () use ($route, $actor): void {
            $before = ['failure_count' => $route->failure_count];

            $route->update(['failure_count' => 0, 'last_failed_at' => null]);

            $this->resolver->forget($route->modelCatalog->slug);
            $this->audit->record($actor, 'model_route.failures_reset', $route->modelCatalog, ['route' => $this->label($route), ...$before], ['failure_count' => 0]);
        });
    }

    public function deleteRoute(ModelRoute $route, SuperAdmin $actor): void
    {
        DB::transaction(function () use ($route, $actor): void {
            $entry = $route->modelCatalog;

            $route->delete();

            $this->resolver->forget($entry->slug);
            $this->audit->record($actor, 'model_route.deleted', $entry, ['route' => $this->label($route)], null);
        });
    }

    private function label(ModelRoute $route): string
    {
        return "{$route->execution_provider}:{$route->execution_model_id}";
    }
}
