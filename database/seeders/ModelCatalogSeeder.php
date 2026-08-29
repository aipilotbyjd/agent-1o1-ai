<?php

namespace Database\Seeders;

use App\Models\Ai\ModelCatalog;
use Illuminate\Database\Seeder;

/**
 * The initial public model catalog shown to end users, each mapped to a
 * primary execution route on whichever direct provider your `.env` already
 * has keys for. Secondary/aggregator routes (OpenRouter, Fireworks,
 * Together) are seeded disabled — turn one on once its credential/API key
 * is configured (see `php artisan model-catalog:add-route` or
 * `App\Models\Ai\ModelRoute`) to give that catalog entry real failover.
 */
class ModelCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed('claude-3-5-sonnet', 'Claude 3.5 Sonnet', 'anthropic', ['context_window' => 200000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-3-5-sonnet-latest', 'priority' => 0, 'is_enabled' => true],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'anthropic/claude-3.5-sonnet', 'priority' => 1, 'is_enabled' => false],
        ]);

        $this->seed('gpt-4o', 'GPT-4o', 'openai', ['context_window' => 128000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 0, 'is_enabled' => true],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'openai/gpt-4o', 'priority' => 1, 'is_enabled' => false],
        ]);

        $this->seed('gpt-4o-mini', 'GPT-4o mini', 'openai', ['context_window' => 128000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o-mini', 'priority' => 0, 'is_enabled' => true],
        ]);

        $this->seed('llama-3.1-405b', 'Llama 3.1 405B', 'meta', ['context_window' => 128000, 'vision' => false, 'tool_use' => true], [
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/llama-v3p1-405b-instruct', 'priority' => 0, 'is_enabled' => false],
            ['execution_provider' => 'together', 'execution_model_id' => 'meta-llama/Meta-Llama-3.1-405B-Instruct-Turbo', 'priority' => 1, 'is_enabled' => false],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'meta-llama/llama-3.1-405b-instruct', 'priority' => 2, 'is_enabled' => false],
        ]);

        $this->seed('workflow-builder-assistant', 'Workflow Builder Assistant', 'internal', ['tool_use' => true], [
            ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 0, 'is_enabled' => true],
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/llama-v3p1-70b-instruct', 'priority' => 1, 'is_enabled' => false],
        ], internal: true);
    }

    /**
     * @param  array<string, mixed>  $capabilities
     * @param  array<int, array<string, mixed>>  $routes
     */
    private function seed(string $slug, string $displayName, string $brand, array $capabilities, array $routes, bool $internal = false): void
    {
        $catalog = ModelCatalog::query()->updateOrCreate(
            ['slug' => $slug],
            ['display_name' => $displayName, 'brand' => $brand, 'capabilities' => $capabilities, 'is_active' => true, 'is_internal' => $internal],
        );

        foreach ($routes as $route) {
            $catalog->routes()->updateOrCreate(
                ['execution_provider' => $route['execution_provider'], 'execution_model_id' => $route['execution_model_id']],
                $route,
            );
        }
    }
}
