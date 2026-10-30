<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskListOrganizationsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_list_organizations';
    }

    public function name(): string
    {
        return 'Zendesk: List Organizations';
    }

    public function description(): string
    {
        return 'Lists the organizations in the account.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/organizations', 'organizations', [], $this->shapeOrganization(...));
    }
}
