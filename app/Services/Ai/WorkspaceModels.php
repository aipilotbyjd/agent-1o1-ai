<?php

namespace App\Services\Ai;

use App\Models\Ai\ModelCatalog;
use App\Models\Ai\WorkspaceModelSettings;

/**
 * A workspace's default model and allowed-model list, as every place that
 * picks or validates a model needs them — see `WorkspaceModelSettings`.
 */
class WorkspaceModels
{
    /**
     * The workspace's default model, when it has one that is still usable
     * (active, public and allowed).
     */
    public function default(string $workspaceId): ?ModelCatalog
    {
        $settings = WorkspaceModelSettings::forWorkspace($workspaceId);

        if ($settings->default_model_catalog_id === null || ! $settings->allows($settings->default_model_catalog_id)) {
            return null;
        }

        return ModelCatalog::query()
            ->whereKey($settings->default_model_catalog_id)
            ->where('is_active', true)
            ->where('is_internal', false)
            ->first();
    }

    public function allows(string $workspaceId, string $modelCatalogId): bool
    {
        return WorkspaceModelSettings::forWorkspace($workspaceId)->allows($modelCatalogId);
    }

    public function allowsSlug(string $workspaceId, string $slug): bool
    {
        $id = ModelCatalog::query()->where('slug', $slug)->value('id');

        return $id === null || $this->allows($workspaceId, $id);
    }
}
