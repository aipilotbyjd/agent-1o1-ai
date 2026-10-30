<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaAnswerNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_answer';
    }

    public function name(): string
    {
        return 'Exa: Answer';
    }

    public function description(): string
    {
        return 'Answers a question from Exa\'s search results, with citations.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::textarea('Question'),
                'model' => Field::select('Model', ['exa', 'exa-fast', 'exa-pro', 'exa-research'], null, 'exa'),
                'include_text' => Field::boolean('Include source text', 'Adds each citation\'s page text.'),
                'system_prompt' => Field::advanced(Field::textarea('Instructions')),
                'output_schema' => Field::advanced(Field::json('Answer shape', 'A JSON schema.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->exa($run, $config, 'POST', '/answer', $this->withoutEmpty([
            'query' => $this->requiredString($config, 'query', 'The question'),
            'model' => $this->textOf($config, 'model'),
            'text' => $this->boolOf($config, 'include_text'),
            'systemPrompt' => $this->textOf($config, 'system_prompt'),
            'outputSchema' => $this->decoded($config['output_schema'] ?? null, 'The answer shape'),
        ]), timeout: 90);

        return ['answer' => $answer['answer'] ?? null, 'citations' => array_map($this->shapeResult(...), array_values(array_filter((array) ($answer['citations'] ?? []), 'is_array'))), 'cost_usd' => data_get($answer, 'costDollars.total'), 'request_id' => $answer['requestId'] ?? null];
    }
}
