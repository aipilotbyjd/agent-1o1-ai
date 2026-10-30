<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskCreateGroupNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_create_group';
    }

    public function name(): string
    {
        return 'Zendesk: Create Group';
    }

    public function description(): string
    {
        return 'Creates an agent group.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
                'description' => Field::advanced(Field::text('Description')),
                'is_public' => Field::advanced(Field::boolean('Public', 'Off makes it a private group.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeGroup((array) ($this->zendesk($run, $config, 'POST', '/groups', [], ['group' => $this->present(['name' => $this->requiredString($config, 'name', 'The name'), 'description' => $this->text($config, 'description'), 'is_public' => isset($config['is_public']) ? filter_var($config['is_public'], FILTER_VALIDATE_BOOLEAN) : null])])['group'] ?? []));
    }
}
