<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomDeleteCompanyNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_delete_company';
    }

    public function name(): string
    {
        return 'Intercom: Delete Company';
    }

    public function description(): string
    {
        return 'Deletes a company. Its contacts stay.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['id'],
            'properties' => [
                ...$this->credentialFields(),
                'id' => Field::text('Company id', 'Intercom\'s id of the company.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'id', 'The company');
        $this->intercom($run, $config, 'DELETE', "/companies/{$id}");

        return ['deleted' => true, 'id' => $id];
    }
}
