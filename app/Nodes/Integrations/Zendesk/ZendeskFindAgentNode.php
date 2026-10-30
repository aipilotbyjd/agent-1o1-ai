<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskFindAgentNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_find_agent';
    }

    public function name(): string
    {
        return 'Zendesk: Find Agent';
    }

    public function description(): string
    {
        return 'Finds one agent or admin by name or email, or says there is none.';
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
                'query' => Field::text('Name or email', null, 'Ada Lovelace'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = preg_replace('/[^\w@.\s+-]/u', '', $this->requiredString($config, 'query', 'The name or email')) ?? '';
        $rows = (array) ($this->zendesk($run, $config, 'GET', '/users/search', ['query' => "role:agent role:admin {$query}"])['users'] ?? []);

        return $rows === [] ? ['found' => false] : ['found' => true, ...$this->shapeUser((array) $rows[0])];
    }
}
