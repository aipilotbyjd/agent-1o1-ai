<?php

namespace App\Nodes\AiAutomation;

use App\Ai\Agents\AdHocPromptAgent;
use App\Contracts\NodeContract;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use App\Services\Ai\ModelCatalogResolver;

/**
 * Gumloop's "Ask AI" node — a provider-agnostic single-turn LLM call routed
 * through `laravel/ai`'s own provider abstraction (see docs/NODES_CATALOG.md's
 * "AI nodes" section).
 */
class AskAiNode implements NodeContract
{
    public function __construct(private readonly ModelCatalogResolver $modelCatalog) {}

    public function type(): string
    {
        return 'ask_ai';
    }

    public function category(): string
    {
        return NodeCategory::AiAutomation->value;
    }

    public function name(): string
    {
        return 'Ask AI';
    }

    public function description(): string
    {
        return 'Prompts an LLM with a single-turn, provider-agnostic call and returns the reply text.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['prompt'],
            'properties' => [
                'instructions' => ['type' => 'string'],
                'prompt' => ['type' => 'string'],
                'model_catalog_slug' => ['type' => 'string'],
                'provider' => ['type' => 'string'],
                'model' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $agent = new AdHocPromptAgent($config['instructions'] ?? 'You are a helpful assistant.');

        if (isset($config['model_catalog_slug'])) {
            $provider = $this->modelCatalog->providerChain($config['model_catalog_slug']);
            $model = null;
        } else {
            $provider = $config['provider'] ?? null;
            $model = $config['model'] ?? null;
        }

        $response = $agent->prompt($config['prompt'], provider: $provider, model: $model);

        return [
            'text' => $response->text,
            // `meta` carries the provider/model that actually served the
            // call, so `CreditMeter` can price it at its real $ cost.
            'usage' => [...$response->usage->toArray(), ...$response->meta->toArray()],
        ];
    }
}
