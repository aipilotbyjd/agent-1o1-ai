<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListTeamsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_teams';
    }

    public function name(): string
    {
        return 'Datadog: List Teams';
    }

    public function description(): string
    {
        return 'Lists teams.';
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
                'keyword' => Field::text('Search', 'Text in the name, handle or a member.'),
                'limit' => Field::integer('Max results', null, 100, 1, 100),
                'page' => Field::advanced(Field::integer('Page', 'Starting at 0.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v2/team', ['filter[keyword]' => $this->text($config, 'keyword'), 'page[size]' => $this->limit($config, 100, 100), 'page[number]' => (int) ($config['page'] ?? 0)]);
        $teams = $this->records($result);

        return ['teams' => $teams, 'count' => count($teams), 'total' => data_get($result, 'meta.pagination.total')];
    }
}
