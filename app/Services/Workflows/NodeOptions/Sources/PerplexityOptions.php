<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;

/**
 * The models of the Agent API and of the Orchestrator API. A search filters the list.
 */
class PerplexityOptions extends HttpOptionsSource
{
    public function sources(): array
    {
        return ['perplexity.models', 'perplexity.orchestrator_models'];
    }

    protected function appName(): string
    {
        return 'Perplexity';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $path = $source === 'perplexity.models' ? '/v1/models' : '/orchestrator/v1/models';
        $models = collect($this->getJson($query, 'https://api.perplexity.ai'.$path)['data'] ?? [])
            ->filter(fn (mixed $model): bool => is_array($model) && isset($model['id']) && $query->matches((string) $model['id']));

        return new NodeOptionsPage($models->map(fn (array $model): NodeOption => new NodeOption((string) $model['id'])));
    }
}
