<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListTeamMembersNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_team_members';
    }

    public function name(): string
    {
        return 'Datadog: List Team Members';
    }

    public function description(): string
    {
        return 'Lists who is in a team.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['team_id'],
            'properties' => [
                ...$this->credentialFields(),
                'team_id' => $this->teamField(),
                'keyword' => Field::text('Search'),
                'limit' => Field::integer('Max results', null, 100, 1, 100),
                'page' => Field::advanced(Field::integer('Page', 'Starting at 0.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'team_id', 'The team');
        $members = $this->records($this->datadog($run, $config, 'GET', "/api/v2/team/{$id}/memberships", ['filter[keyword]' => $this->text($config, 'keyword'), 'page[size]' => $this->limit($config, 100, 100), 'page[number]' => (int) ($config['page'] ?? 0)]));

        return ['members' => $members, 'count' => count($members)];
    }
}
