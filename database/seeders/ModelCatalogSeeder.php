<?php

namespace Database\Seeders;

use App\Models\Ai\ModelCatalog;
use Illuminate\Database\Seeder;

/**
 * The public model catalog shown to end users, current as of the August
 * 2026 model landscape (researched via web search: see the per-brand
 * comments below for what's confirmed vs. best-effort).
 *
 * Each entry's primary route is on whichever direct provider your `.env`
 * already has keys for. Secondary/aggregator routes (OpenRouter, Fireworks,
 * Together, Bedrock) are seeded disabled: turn one on once its
 * credential/API key is configured (see `php artisan model-catalog:add-route`
 * or `App\Models\Ai\ModelRoute`) to give that catalog entry real failover.
 *
 * Open-weight aggregator model ids (Kimi K3, GLM-5.2, MiniMax M3, Qwen3.6,
 * Llama 4 Maverick) are best-effort slugs inferred from provider naming
 * conventions, not confirmed against Fireworks/Together/OpenRouter's own
 * catalogs: verify the exact string in that provider's docs before
 * enabling one of these routes.
 */
class ModelCatalogSeeder extends Seeder
{
    /**
     * What the model picker says about each entry. Speed and cost tiers place
     * a model relative to the rest of this catalog (1 = cheapest), not against
     * outside benchmarks.
     *
     * @var array<string, array{description: string, strengths: array<int, string>, speed: string, cost_tier: int}>
     */
    private const array DETAILS = [
        'claude-fable-5' => ['description' => 'Anthropic\'s top model for the hardest reasoning, research and long, careful work.', 'strengths' => ['Deep reasoning', 'Long documents', 'Careful writing'], 'speed' => 'thorough', 'cost_tier' => 3],
        'claude-opus-5' => ['description' => 'Anthropic\'s most capable everyday model for complex, multi-step tasks and agents.', 'strengths' => ['Complex tasks', 'Coding', 'Agents with many tools'], 'speed' => 'thorough', 'cost_tier' => 3],
        'claude-sonnet-5' => ['description' => 'A balanced Anthropic model: strong quality at a moderate cost; a good default for agents.', 'strengths' => ['All-round', 'Coding', 'Tool use'], 'speed' => 'balanced', 'cost_tier' => 2],
        'claude-haiku-4-5' => ['description' => 'Anthropic\'s fast, low-cost model for simple, high-volume work.', 'strengths' => ['Speed', 'Low cost', 'Classification'], 'speed' => 'fast', 'cost_tier' => 1],
        'gpt-5.6-sol' => ['description' => 'OpenAI\'s most capable GPT-5.6 tier for demanding reasoning and long context.', 'strengths' => ['Deep reasoning', 'Very long context', 'Coding'], 'speed' => 'thorough', 'cost_tier' => 3],
        'gpt-5.6-terra' => ['description' => 'The balanced GPT-5.6 tier for everyday agent work.', 'strengths' => ['All-round', 'Tool use', 'Writing'], 'speed' => 'balanced', 'cost_tier' => 2],
        'gpt-5.6-luna' => ['description' => 'The fast, lightweight GPT-5.6 tier for quick, low-cost tasks.', 'strengths' => ['Speed', 'Low cost', 'Summaries'], 'speed' => 'fast', 'cost_tier' => 1],
        'gpt-4o' => ['description' => 'An established OpenAI multimodal model that handles text and images.', 'strengths' => ['Images', 'All-round', 'Reliable'], 'speed' => 'balanced', 'cost_tier' => 2],
        'gpt-4o-mini' => ['description' => 'A small, very cheap OpenAI model for simple tasks at volume.', 'strengths' => ['Low cost', 'Speed', 'Simple tasks'], 'speed' => 'fast', 'cost_tier' => 1],
        'gemini-3.1-pro' => ['description' => 'Google\'s capable Gemini model with a very long context window.', 'strengths' => ['Very long context', 'Images', 'Research'], 'speed' => 'thorough', 'cost_tier' => 2],
        'gemini-3.5-flash' => ['description' => 'A fast Gemini model with a long context window at low cost.', 'strengths' => ['Speed', 'Long context', 'Low cost'], 'speed' => 'fast', 'cost_tier' => 1],
        'grok-4.6' => ['description' => 'xAI\'s Grok model for reasoning and conversational tasks.', 'strengths' => ['Reasoning', 'Conversation', 'Images'], 'speed' => 'balanced', 'cost_tier' => 2],
        'mistral-medium-3.5' => ['description' => 'Mistral\'s mid-size model for solid quality at a moderate cost.', 'strengths' => ['All-round', 'Multilingual', 'Tool use'], 'speed' => 'balanced', 'cost_tier' => 2],
        'mistral-small-4' => ['description' => 'Mistral\'s small, fast model: inexpensive and good for straightforward agents.', 'strengths' => ['Speed', 'Low cost', 'Multilingual'], 'speed' => 'fast', 'cost_tier' => 1],
        'deepseek-v4-pro' => ['description' => 'DeepSeek\'s strong reasoning and coding model with a very long context.', 'strengths' => ['Reasoning', 'Coding', 'Very long context'], 'speed' => 'thorough', 'cost_tier' => 2],
        'deepseek-v4-flash' => ['description' => 'A fast, low-cost DeepSeek model for everyday tasks.', 'strengths' => ['Speed', 'Low cost', 'Coding'], 'speed' => 'fast', 'cost_tier' => 1],
        'llama-4-maverick' => ['description' => 'Meta\'s open-weight Llama 4 model with image understanding.', 'strengths' => ['Open weights', 'Images', 'Long context'], 'speed' => 'balanced', 'cost_tier' => 1],
        'llama-3.1-405b' => ['description' => 'Meta\'s large open-weight Llama 3.1 model.', 'strengths' => ['Open weights', 'All-round'], 'speed' => 'thorough', 'cost_tier' => 2],
        'kimi-k3' => ['description' => 'Moonshot\'s Kimi model with a very long context window.', 'strengths' => ['Very long context', 'Images', 'Agents'], 'speed' => 'balanced', 'cost_tier' => 2],
        'glm-5.2' => ['description' => 'Zhipu\'s GLM model for reasoning and tool use.', 'strengths' => ['Reasoning', 'Tool use', 'Long context'], 'speed' => 'balanced', 'cost_tier' => 1],
        'minimax-m3' => ['description' => 'MiniMax\'s long-context model for agents and documents.', 'strengths' => ['Long context', 'Agents'], 'speed' => 'balanced', 'cost_tier' => 1],
        'qwen3.6' => ['description' => 'Alibaba\'s Qwen model: capable and inexpensive.', 'strengths' => ['Low cost', 'Multilingual', 'Coding'], 'speed' => 'balanced', 'cost_tier' => 1],
    ];

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
            // scheme: verify the exact cross-region inference profile id
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

        // Kept as still-available previous-generation entries: agents
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

        $this->seed('mistral-small-4', 'Mistral Small 4', 'mistral', ['context_window' => 256000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'xkiro', 'execution_model_id' => 'mistralai/mistral-small-2603', 'priority' => 0, 'is_enabled' => true],
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

        // --- Meta (Llama): aggregator-only, no direct Meta API ------------
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

        // --- Moonshot AI (Kimi): aggregator-only --------------------------
        $this->seed('kimi-k3', 'Kimi K3', 'moonshot', ['context_window' => 1000000, 'vision' => true, 'tool_use' => true], [
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/kimi-k3', 'priority' => 0, 'is_enabled' => false],
            ['execution_provider' => 'together', 'execution_model_id' => 'moonshotai/Kimi-K3', 'priority' => 1, 'is_enabled' => false],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'moonshotai/kimi-k3', 'priority' => 2, 'is_enabled' => false],
        ]);

        // --- Zhipu (GLM): aggregator-only ----------------------------------
        $this->seed('glm-5.2', 'GLM-5.2', 'zhipu', ['context_window' => 1040000, 'vision' => false, 'tool_use' => true], [
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/glm-5p2', 'priority' => 0, 'is_enabled' => false],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'z-ai/glm-5.2', 'priority' => 1, 'is_enabled' => false],
        ]);

        // --- MiniMax: aggregator-only ---------------------------------------
        $this->seed('minimax-m3', 'MiniMax M3', 'minimax', ['context_window' => 1000000, 'vision' => false, 'tool_use' => true], [
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/minimax-m3', 'priority' => 0, 'is_enabled' => false],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'minimax/minimax-m3', 'priority' => 1, 'is_enabled' => false],
        ]);

        // --- Alibaba (Qwen): aggregator-only --------------------------------
        $this->seed('qwen3.6', 'Qwen3.6', 'alibaba', ['context_window' => 256000, 'vision' => false, 'tool_use' => true], [
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/qwen3p6-235b-a22b-instruct', 'priority' => 0, 'is_enabled' => false],
            ['execution_provider' => 'openrouter', 'execution_model_id' => 'qwen/qwen3.6-235b-a22b', 'priority' => 1, 'is_enabled' => false],
        ]);

        // Internal-only, never shown in the public model picker (see
        // `ModelCatalog::is_internal`/`ModelCatalogController::index()`).
        // Powers the workflow-builder chat (`ProcessWorkflowBuilderMessageJob`) and its assist helpers (`WorkflowBuilderAssistant`).
        $this->seed('workflow-builder-assistant', 'Workflow Builder Assistant', 'internal', ['tool_use' => true], [
            ['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 1, 'is_enabled' => true],
            ['execution_provider' => 'fireworks', 'execution_model_id' => 'accounts/fireworks/models/llama-v3p1-70b-instruct', 'priority' => 0, 'is_enabled' => true],
        ], internal: true);

        // Internal-only: the personal assistant's default model when its
        // owner hasn't picked one (`TurnRunner::modelFor()`). Operator-fixed:
        // change it with `model-catalog:add-route personal-assistant …`.
        // Mistral Small 4 (via xkiro) for now; Claude Sonnet 5 is seeded
        // disabled, ready to switch on once an Anthropic key is configured.
        $this->seed('personal-assistant', 'Personal Assistant', 'internal', ['tool_use' => true], [
            ['execution_provider' => 'xkiro', 'execution_model_id' => 'mistralai/mistral-small-2603', 'priority' => 0, 'is_enabled' => true],
            ['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-sonnet-5', 'priority' => 1, 'is_enabled' => false],
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
            ['display_name' => $displayName, 'brand' => $brand, 'capabilities' => $capabilities, 'is_active' => true, 'is_internal' => $internal, ...(self::DETAILS[$slug] ?? [])],
        );

        foreach ($routes as $route) {
            $catalog->routes()->updateOrCreate(
                ['execution_provider' => $route['execution_provider'], 'execution_model_id' => $route['execution_model_id']],
                $route,
            );
        }
    }
}
