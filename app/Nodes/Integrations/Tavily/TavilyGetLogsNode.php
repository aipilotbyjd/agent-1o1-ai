<?php

namespace App\Nodes\Integrations\Tavily;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TavilyGetLogsNode extends AbstractTavilyNode
{
    public function type(): string
    {
        return 'tavily_get_logs';
    }

    public function name(): string
    {
        return 'Tavily: Get Logs';
    }

    public function description(): string
    {
        return 'Reads the account\'s recent requests with their cost, newest first.';
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
                'limit' => Field::integer('Max results', null, 10, 1, 1000),
                'start_date' => Field::advanced(Field::text('From date', null, '2026-01-31')),
                'end_date' => Field::advanced(Field::text('To date', null, '2026-12-31')),
                'endpoints' => Field::advanced(Field::list('Kinds of request', 'search, extract, map, crawl, research.')),
                'project_id' => Field::advanced(Field::text('Project id')),
                'filter_by_api_key' => Field::advanced(Field::boolean('Only this key')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->tavily($run, $config, 'POST', '/logs', $this->withoutEmpty([
            'limit' => $this->intOf($config, 'limit'),
            'start_date' => $this->textOf($config, 'start_date'),
            'end_date' => $this->textOf($config, 'end_date'),
            'endpoints' => $this->stringList($config['endpoints'] ?? []),
            'project_id' => $this->textOf($config, 'project_id'),
            'filter_by_api_key' => $this->boolOf($config, 'filter_by_api_key'),
        ]));

        return ['logs' => $answer['logs'] ?? [], 'count' => $answer['count'] ?? null];
    }
}
