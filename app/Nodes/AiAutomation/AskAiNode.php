<?php

namespace App\Nodes\AiAutomation;

use App\Ai\Agents\AdHocPromptAgent;
use App\Contracts\DeclaresEffect;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Ai\ModelCatalogResolver;

/**
 * Gumloop's "Ask AI" node — a provider-agnostic single-turn LLM call routed
 * through `laravel/ai`'s own provider abstraction (see docs/NODES_CATALOG.md's
 * "AI nodes" section).
 */
class AskAiNode implements DeclaresEffect, HasIcon, NodeContract
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

    public function icon(): string
    {
        return 'ai-chat-02';
    }

    public function description(): string
    {
        return 'Prompts an LLM with a single-turn, provider-agnostic call and returns the reply text.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['prompt'],
            'properties' => [
                'model_catalog_slug' => Field::dynamic('Model', 'ai.models', 'Leave empty to use the default model.', allowCustom: false),
                'instructions' => Field::advanced(Field::textarea('Instructions', 'How the AI should behave (system prompt).', 'You are a helpful assistant.')),
                'prompt' => Field::textarea('Prompt', 'What to ask. Use {{templates}} to include data from earlier steps.'),
                'provider' => Field::hidden('Legacy provider override; use Model instead.'),
                'model' => Field::hidden('Legacy model override; use Model instead.'),
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
