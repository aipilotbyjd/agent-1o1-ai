<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskListUserOrganizationsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_list_user_organizations';
    }

    public function name(): string
    {
        return 'Zendesk: List User Organizations';
    }

    public function description(): string
    {
        return 'Lists the organizations a person belongs to.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'user_id' => Field::text('User id'),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'user_id', 'The user');

        return $this->listing($run, $config, "/users/{$id}/organizations", 'organizations', [], $this->shapeOrganization(...));
    }
}
