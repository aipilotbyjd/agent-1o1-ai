<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

/**
 * Perplexity ended support for Sonar Chat Completions on 27 September 2026. Synchronous requests keep working, so this stays for flows that want a Sonar model. Its async mode is gone: research goes through Start Research.
 */
class PerplexityChatCompletionNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_chat_completion';
    }

    public function name(): string
    {
        return 'Perplexity: Chat Completion';
    }

    public function description(): string
    {
        return 'Asks a Sonar model, which searches the web and answers with citations. Sonar is Perplexity\'s older API, still answering through the Agent API: prefer Create Agent Response for new flows.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'model' => Field::select('Model', ['sonar', 'sonar-pro', 'sonar-reasoning-pro'], null, 'sonar'),
                'prompt' => Field::textarea('Question', 'Or give the whole conversation in Messages.'),
                'system' => Field::textarea('Instructions'),
                'messages' => Field::advanced(Field::json('Messages', 'The whole conversation: a list of {"role": "user", "content": "..."}.', 'array')),
                'temperature' => Field::advanced(Field::integer('Temperature', 'From 0 to less than 2.', null, 0, 2)),
                'max_tokens' => Field::advanced(Field::integer('Max answer length (tokens)', null, null, 1)),
                'search_recency_filter' => Field::advanced(Field::select('From the last', ['hour', 'day', 'week', 'month', 'year'])),
                'search_domain_filter' => Field::advanced(Field::list('Sites', 'Only these sites, or put - before a site to leave it out.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeChat($this->perplexity($run, $config, 'POST', '/v1/sonar', $this->withoutEmpty([
            'model' => $this->textOf($config, 'model') ?? 'sonar',
            'messages' => $this->messages($config),
            'temperature' => $this->floatOf($config, 'temperature'),
            'max_tokens' => $this->intOf($config, 'max_tokens'),
            'search_recency_filter' => $this->textOf($config, 'search_recency_filter'),
            'search_domain_filter' => $this->stringList($config['search_domain_filter'] ?? []),
        ]), timeout: self::ANSWER_TIMEOUT_SECONDS));
    }
}
