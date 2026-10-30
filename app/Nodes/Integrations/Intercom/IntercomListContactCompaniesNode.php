<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomListContactCompaniesNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_contact_companies';
    }

    public function name(): string
    {
        return 'Intercom: List Contact\'s Companies';
    }

    public function description(): string
    {
        return 'Lists the companies a contact belongs to.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::text('Contact id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'contact_id', 'The contact');
        $rows = (array) ($this->intercom($run, $config, 'GET', "/contacts/{$id}/companies")['data'] ?? []);

        return ['companies' => array_map(fn (mixed $company): array => $this->shapeCompany((array) $company), $rows), 'count' => count($rows)];
    }
}
