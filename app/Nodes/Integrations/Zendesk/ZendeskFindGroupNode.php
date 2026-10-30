<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskFindGroupNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_find_group';
    }

    public function name(): string
    {
        return 'Zendesk: Find Group';
    }

    public function description(): string
    {
        return 'Finds an agent group by the start of its name, or says there is none.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name starts with', null, 'Support'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $name = mb_strtolower($this->requiredString($config, 'name', 'The name'));
        $found = array_values(array_filter($this->listAll($run, $config, '/groups', 'groups', [], 1000), fn (array $group): bool => str_starts_with(mb_strtolower((string) ($group['name'] ?? '')), $name)));

        return $found === [] ? ['found' => false] : ['found' => true, ...$this->shapeGroup($found[0])];
    }
}
