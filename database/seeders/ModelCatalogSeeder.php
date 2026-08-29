<?php

namespace Database\Seeders;

use App\Models\Ai\ModelCatalog;
use Illuminate\Database\Seeder;

/**
 * The public model catalog shown to end users, current as of the August
 * 2026 model landscape (researched via web search — see the per-brand
 * comments below for what's confirmed vs. best-effort).
 *
 * Each entry's primary route is on whichever direct provider your `.env`
 * already has keys for. Secondary/aggregator routes (OpenRouter, Fireworks,
 * Together, Bedrock) are seeded disabled — turn one on once its
 * credential/API key is configured (see `php artisan model-catalog:add-route`
 * or `App\Models\Ai\ModelRoute`) to give that catalog entry real failover.
 *
 * Open-weight aggregator model ids (Kimi K3, GLM-5.2, MiniMax M3, Qwen3.6,
 * Llama 4 Maverick) are best-effort slugs inferred from provider naming
 * conventions, not confirmed against Fireworks/Together/OpenRouter's own
 * catalogs — verify the exact string in that provider's docs before
 * enabling one of these routes.
 */
class ModelCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // --- Anthropic ---------------------------------------------------
        $this->seed('claude-fable-5', 'Claude Fable 5', 'anthropic', ['context_window' => 200000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-fable-5', 'priority' => 0, 'is_enabled' => true],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'anthropic/claude-fable-5', 'priority' => 1, 'is_enabled' => false],
        ]);

        $this->seed('claude-opus-5', 'Claude Opus 5', 'anthropic', ['context_window' => 200000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-opus-5', 'priority' => 0, 'is_enabled' => true],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'anthropic/claude-opus-5', 'priority' => 1, 'is_enabled' => false],
        ]);

        $this->seed('claude-sonnet-5', 'Claude Sonnet 5', 'anthropic', ['context_window' => 200000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-sonnet-5', 'priority' => 0, 'is_enabled' => true],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'anthropic/claude-sonnet-5', 'priority' => 1, 'is_enabled' => false],
            // Bedrock also hosts Anthropic models under its own naming
            // scheme — verify the exact cross-region inference profile id
            // before enabling.
            ['execution_provider' => 'bedrock', 'execution_model_id' => 'anthropic.claude-sonnet-5-v1:0', 'priority' => 2, 'is_enabled' => false],
        ]);

        $this->seed('claude-haiku-4-5', 'Claude Haiku 4.5', 'anthropic', ['context_window' => 200000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-haiku-4-5-20251001', 'priority' => 0, 'is_enabled' => true],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'anthropic/claude-haiku-4.5', 'priority' => 1, 'is_enabled' => false],
        ]);

        // --- OpenAI --------------------------------------------------------
        $this->seed('gpt-5.6-sol', 'GPT-5.6 Sol', 'openai', ['context_window' => 1050000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-5.6-sol', 'priority' => 0, 'is_enabled' => true],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'openai/gpt-5.6-sol', 'priority' => 1, 'is_enabled' => false],
        ]);

        $this->seed('gpt-5.6-terra', 'GPT-5.6 Terra', 'openai', ['context_window' => 1050000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-5.6-terra', 'priority' => 0, 'is_enabled' => true],
        ]);

        $this->seed('gpt-5.6-luna', 'GPT-5.6 Luna', 'openai', ['context_window' => 1050000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-5.6-luna', 'priority' => 0, 'is_enabled' => true],
        ]);

        // Kept as still-available previous-generation entries — agents
        // already pointed at these keep working unchanged.
        $this->seed('gpt-4o', 'GPT-4o', 'openai', ['context_window' => 128000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 0, 'is_enabled' => true],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'openai/gpt-4o', 'priority' => 1, 'is_enabled' => false],
        ]);

        $this->seed('gpt-4o-mini', 'GPT-4o mini', 'openai', ['context_window' => 128000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o-mini', 'priority' => 0, 'is_enabled' => true],
        ]);

        // --- Google ----------------------------------------------------
        $this->seed('gemini-3.1-pro', 'Gemini 3.1 Pro', 'google', ['context_window' => 1000000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'gemini', 'execution_model_id' => 'gemini-3.1-pro', 'priority' => 0, 'is_enabled' => true],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'google/gemini-3.1-pro', 'priority' => 1, 'is_enabled' => false],
        ]);

        $this->seed('gemini-3.5-flash', 'Gemini 3.5 Flash', 'google', ['context_window' => 1000000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'gemini', 'execution_model_id' => 'gemini-3.5-flash', 'priority' => 0, 'is_enabled' => true],
        ]);

        // --- xAI ---------------------------------------------------------
        $this->seed('grok-4.6', 'Grok 4.6', 'xai', ['context_window' => 500000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'xai', 'execution_model_id' => 'grok-4.6', 'priority' => 0, 'is_enabled' => true],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'x-ai/grok-4.6', 'priority' => 1, 'is_enabled' => false],
        ]);

        // --- Mistral -------------------------------------------------------
        $this->seed('mistral-medium-3.5', 'Mistral Medium 3.5', 'mistral', ['context_window' => 128000, 'vision' => false, 'tool_use' => true], [
            ['execution_provider' => 'mistral', 'execution_model_id' => 'mistral-medium-3.5', 'priority' => 0, 'is_enabled' => true],
        ]);

        // --- DeepSeek (has its own direct API, plus aggregator routes) -----
        $this->seed('deepseek-v4-pro', 'DeepSeek V4 Pro', 'deepseek', ['context_window' => 1040000, 'vision' => false, 'tool_use' => true], [
            ['execution_provider' => 'deepseek', 'execution_model_id' => 'deepseek-v4-pro', 'priority' => 0, 'is_enabled' => true],
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/deepseek-v4p', 'priority' => 1, 'is_enabled' => false],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'deepseek/deepseek-v4-pro', 'priority' => 2, 'is_enabled' => false],
        ]);

        $this->seed('deepseek-v4-flash', 'DeepSeek V4 Flash', 'deepseek', ['context_window' => 1000000, 'vision' => false, 'tool_use' => true], [
            ['execution_provider' => 'deepseek', 'execution_model_id' => 'deepseek-v4-flash', 'priority' => 0, 'is_enabled' => true],
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/deepseek-v4-flash', 'priority' => 1, 'is_enabled' => false],
        ]);

        // --- Meta (Llama) — aggregator-only, no direct Meta API ------------
        $this->seed('llama-4-maverick', 'Llama 4 Maverick', 'meta', ['context_window' => 1000000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/llama4-maverick-instruct-basic', 'priority' => 0, 'is_enabled' => false],
            ['execution_provider' => 'together', 'execution_model_id' => 'meta-llama/Llama-4-Maverick-17B-128E-Instruct', 'priority' => 1, 'is_enabled' => false],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'meta-llama/llama-4-maverick', 'priority' => 2, 'is_enabled' => false],
        ]);

        $this->seed('llama-3.1-405b', 'Llama 3.1 405B', 'meta', ['context_window' => 128000, 'vision' => false, 'tool_use' => true], [
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/llama-v3p1-405b-instruct', 'priority' => 0, 'is_enabled' => false],
            ['execution_provider' => 'together', 'execution_model_id' => 'meta-llama/Meta-Llama-3.1-405B-Instruct-Turbo', 'priority' => 1, 'is_enabled' => false],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'meta-llama/llama-3.1-405b-instruct', 'priority' => 2, 'is_enabled' => false],
        ]);

        // --- Moonshot AI (Kimi) — aggregator-only --------------------------
        $this->seed('kimi-k3', 'Kimi K3', 'moonshot', ['context_window' => 1000000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/kimi-k3', 'priority' => 0, 'is_enabled' => false],
            ['execution_provider' => 'together', 'execution_model_id' => 'moonshotai/Kimi-K3', 'priority' => 1, 'is_enabled' => false],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'moonshotai/kimi-k3', 'priority' => 2, 'is_enabled' => false],
        ]);

        // --- Zhipu (GLM) — aggregator-only ----------------------------------
        $this->seed('glm-5.2', 'GLM-5.2', 'zhipu', ['context_window' => 1040000, 'vision' => false, 'tool_use' => true], [
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/glm-5p2', 'priority' => 0, 'is_enabled' => false],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'z-ai/glm-5.2', 'priority' => 1, 'is_enabled' => false],
        ]);

        // --- MiniMax — aggregator-only ---------------------------------------
        $this->seed('minimax-m3', 'MiniMax M3', 'minimax', ['context_window' => 1000000, 'vision' => false, 'tool_use' => true], [
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/minimax-m3', 'priority' => 0, 'is_enabled' => false],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'minimax/minimax-m3', 'priority' => 1, 'is_enabled' => false],
        ]);

        // --- Alibaba (Qwen) — aggregator-only --------------------------------
        $this->seed('qwen3.6', 'Qwen3.6', 'alibaba', ['context_window' => 256000, 'vision' => false, 'tool_use' => true], [
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/qwen3p6-235b-a22b-instruct', 'priority' => 0, 'is_enabled' => false],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'qwen/qwen3.6-235b-a22b', 'priority' => 1, 'is_enabled' => false],
        ]);

        // Internal-only — never shown in the public model picker (see
        // `ModelCatalog::is_internal`/`ModelCatalogController::index()`).
        // Powers `SendWorkflowBuilderMessageAction`'s workflow-builder chat.
        $this->seed('workflow-builder-assistant', 'Workflow Builder Assistant', 'internal', ['tool_use' => true], [
            ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 1, 'is_enabled' => true],
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/llama-v3p1-70b-instruct', 'priority' => 0, 'is_enabled' => true],
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
