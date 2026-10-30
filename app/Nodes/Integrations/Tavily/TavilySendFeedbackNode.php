<?php

namespace App\Nodes\Integrations\Tavily;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TavilySendFeedbackNode extends AbstractTavilyNode
{
    public function type(): string
    {
        return 'tavily_send_feedback';
    }

    public function name(): string
    {
        return 'Tavily: Send Feedback';
    }

    public function description(): string
    {
        return 'Tells Tavily how good a request\'s results were.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['request_id'],
            'properties' => [
                ...$this->credentialFields(),
                'request_id' => Field::text('Request id'),
                'human_score' => Field::integer('Score', 'From 0 to 1.', null, 0, 1),
                'comment' => Field::textarea('Comment'),
                'used_urls' => Field::advanced(Field::list('Addresses that were used')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->tavily($run, $config, 'POST', '/feedback', $this->withoutEmpty([
            'request_id' => $this->requiredString($config, 'request_id', 'The request id'),
            'human_score' => $this->floatOf($config, 'human_score'),
            'comment' => $this->textOf($config, 'comment'),
            'used_urls' => $this->stringList($config['used_urls'] ?? []),
        ]));

        return ['feedback_id' => $answer['feedback_id'] ?? null, 'sent' => (bool) ($answer['success'] ?? true)];
    }
}
