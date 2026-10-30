<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PerplexityOrchestratorChatNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_orchestrator_chat';
    }

    public function name(): string
    {
        return 'Perplexity: Orchestrator Chat';
    }

    public function description(): string
    {
        return 'Sends a conversation to any Orchestrator API model, in the OpenAI Chat Completions format. No web search.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['model'],
            'properties' => [
                ...$this->credentialFields(),
                'model' => Field::dynamic('Model', 'perplexity.orchestrator_models', null, 'Pick a model.'),
                'prompt' => Field::textarea('Message', 'Or give the whole conversation in Messages.'),
                'system' => Field::textarea('Instructions'),
                'messages' => Field::advanced(Field::json('Messages', 'The whole conversation: a list of {"role": "user", "content": "..."}.', 'array')),
                'temperature' => Field::advanced(Field::integer('Temperature', null, null, 0, 2)),
                'max_tokens' => Field::advanced(Field::integer('Max answer length (tokens)', null, null, 1)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeChat($this->perplexity($run, $config, 'POST', '/orchestrator/v1/chat/completions', $this->withoutEmpty([
            'model' => $this->requiredString($config, 'model', 'The model'),
            'messages' => $this->messages($config),
            'temperature' => $this->floatOf($config, 'temperature'),
            'max_tokens' => $this->intOf($config, 'max_tokens'),
        ]), timeout: self::ANSWER_TIMEOUT_SECONDS));
    }
}
