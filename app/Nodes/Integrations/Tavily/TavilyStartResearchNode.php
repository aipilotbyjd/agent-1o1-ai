<?php

namespace App\Nodes\Integrations\Tavily;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TavilyStartResearchNode extends AbstractTavilyNode
{
    public function type(): string
    {
        return 'tavily_start_research';
    }

    public function name(): string
    {
        return 'Tavily: Start Research';
    }

    public function description(): string
    {
        return 'Starts multi-source research on a question and returns the id at once. Read the report with Get Research.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['input'],
            'properties' => [
                ...$this->credentialFields(),
                'input' => Field::textarea('What to research'),
                'model' => Field::select('Depth', ['auto', 'mini', 'pro'], 'Mini is narrower and faster, pro is broader.', 'auto'),
                'output_length' => Field::select('Length', ['short', 'standard', 'long'], null, 'standard'),
                'citation_format' => Field::advanced(Field::select('Citations', ['numbered', 'mla', 'apa', 'chicago'], null, 'numbered')),
                'output_schema' => Field::advanced(Field::json('Result shape', 'A JSON schema for the result.')),
                'include_domains' => Field::advanced(Field::list('Only these sites')),
                'exclude_domains' => Field::advanced(Field::list('Not these sites')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->tavily($run, $config, 'POST', '/research', $this->withoutEmpty([
            'input' => $this->requiredString($config, 'input', 'What to research'),
            'model' => $this->textOf($config, 'model'),
            'output_length' => $this->textOf($config, 'output_length'),
            'citation_format' => $this->textOf($config, 'citation_format'),
            'output_schema' => $this->decoded($config['output_schema'] ?? null, 'The result shape'),
            'include_domains' => $this->stringList($config['include_domains'] ?? []),
            'exclude_domains' => $this->stringList($config['exclude_domains'] ?? []),
        ]));

        return ['id' => $answer['request_id'] ?? null, 'status' => $answer['status'] ?? null, 'model' => $answer['model'] ?? null, 'created_at' => $answer['created_at'] ?? null];
    }
}
