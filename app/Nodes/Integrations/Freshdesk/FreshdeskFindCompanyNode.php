<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskFindCompanyNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_find_company';
    }

    public function name(): string
    {
        return 'Freshdesk: Find Company';
    }

    public function description(): string
    {
        return 'Finds companies by the start of their name.';
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
                'name' => Field::text('Name starts with', null, 'Acme'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = (array) ($this->freshdesk($run, $config, 'GET', '/companies/autocomplete', ['name' => $this->requiredString($config, 'name', 'The name')])['companies'] ?? []);

        return ['companies' => $rows, 'count' => count($rows)];
    }
}
