<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomUpdateCompanyNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_update_company';
    }

    public function name(): string
    {
        return 'Intercom: Update Company';
    }

    public function description(): string
    {
        return 'Changes a company by Intercom\'s id for it. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['id'],
            'properties' => [
                ...$this->credentialFields(),
                'id' => Field::text('Company id', 'Intercom\'s id of the company.'),
                ...$this->companyFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'id', 'The company');
        $body = $this->companyBody($config);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeCompany($this->intercom($run, $config, 'PUT', "/companies/{$id}", [], $body));
    }
}
