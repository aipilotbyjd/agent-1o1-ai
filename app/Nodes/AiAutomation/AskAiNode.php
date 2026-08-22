<?php

namespace App\Nodes\AiAutomation;

use App\Ai\Agents\AdHocPromptAgent;
use App\Contracts\NodeContract;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;

/**
 * Gumloop's "Ask AI" node — a provider-agnostic single-turn LLM call routed
 * through `laravel/ai`'s own provider abstraction (see docs/NODES_CATALOG.md's
 * "AI nodes" section).
 */
class AskAiNode implements NodeContract
{
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
                'provider' => ['type' => 'string'],
                'model' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(array $config = []): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => ['type' => 'string'],
                'usage' => [
                    'type' => 'object',
                    'properties' => [
                        'input_tokens' => ['type' => 'integer'],
                        'output_tokens' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $agent = new AdHocPromptAgent($config['instructions'] ?? 'You are a helpful assistant.');

        $response = $agent->prompt(
            $config['prompt'],
            provider: $config['provider'] ?? null,
            model: $config['model'] ?? null,
        );

        return [
            'text' => $response->text,
            'usage' => $response->usage->toArray(),
        ];
    }
}
