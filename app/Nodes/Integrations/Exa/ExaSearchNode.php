<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaSearchNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_search';
    }

    public function name(): string
    {
        return 'Exa: Search';
    }

    public function description(): string
    {
        return 'Searches the web by meaning or keyword, with filters, and can read each result\'s page. Deep types research the question and can answer in a set shape.';
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
                'query' => Field::textarea('Search for'),
                'type' => Field::select('Search type', ['auto', 'fast', 'instant', 'lite', 'deep-lite', 'deep', 'deep-reasoning'], 'Deep types take longer and cost more.', 'auto'),
                ...$this->filterFields(),
                ...$this->contentFields(),
                'additional_queries' => Field::advanced(Field::list('More searches', 'Other wordings, for the deep types.')),
                'output_schema' => Field::advanced(Field::json('Answer shape', 'A JSON schema, for the deep types.')),
                'system_prompt' => Field::advanced(Field::textarea('Instructions', 'How to research, for the deep types.')),
                'country' => Field::advanced(Field::text('Country', 'A two-letter code.', 'US')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $contents = $this->contentOptions($config);
        $country = $this->textOf($config, 'country');
        $answer = $this->exa($run, $config, 'POST', '/search', $this->withoutEmpty([
            'query' => $this->requiredString($config, 'query', 'What to search for'),
            'type' => $this->textOf($config, 'type'),
            ...$this->filterOptions($config),
            'contents' => $contents,
            'additionalQueries' => $this->stringList($config['additional_queries'] ?? []),
            'outputSchema' => $this->decoded($config['output_schema'] ?? null, 'The answer shape'),
            'systemPrompt' => $this->textOf($config, 'system_prompt'),
            'userLocation' => $country === null ? null : ['country' => $country],
        ]), timeout: 90);

        return $this->shapeResults($answer);
    }
}
