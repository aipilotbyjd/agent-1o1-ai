<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomGetCompanyNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_get_company';
    }

    public function name(): string
    {
        return 'Intercom: Get Company';
    }

    public function description(): string
    {
        return 'Reads a company by Intercom\'s id for it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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

        return $this->findOne($run, $config, "/companies/{$id}", $id, $this->shapeCompany(...));
    }
}
