<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskListContactFieldsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_contact_fields';
    }

    public function name(): string
    {
        return 'Freshdesk: List Contact Fields';
    }

    public function description(): string
    {
        return 'Lists the system and custom fields of contacts.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->freshdesk($run, $config, 'GET', '/contact_fields');

        return ['contact_fields' => array_values($rows), 'count' => count($rows)];
    }
}
