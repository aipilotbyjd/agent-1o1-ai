<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListUsersNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_users';
    }

    public function name(): string
    {
        return 'Datadog: List Users';
    }

    public function description(): string
    {
        return 'Lists the users of the organization.';
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
                'filter' => Field::text('Search', 'Text in the name or email.'),
                'status' => Field::advanced(Field::text('Status', 'Comma-separated: Active, Pending, Disabled.')),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
                'page' => Field::advanced(Field::integer('Page', 'Starting at 0.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v2/users', ['filter' => $this->text($config, 'filter'), 'filter[status]' => $this->text($config, 'status'), 'page[size]' => $this->limit($config, 100), 'page[number]' => (int) ($config['page'] ?? 0)]);
        $users = $this->records($result);

        return ['users' => $users, 'count' => count($users), 'total' => data_get($result, 'meta.page.total_count')];
    }
}
