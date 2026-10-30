<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskSearchOrganizationsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_search_organizations';
    }

    public function name(): string
    {
        return 'Zendesk: Search Organizations';
    }

    public function description(): string
    {
        return 'Finds organizations whose name starts with some text, or the one with an external id.';
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
                'name' => Field::text('Name starts with', null, 'Acme'),
                'external_id' => Field::advanced(Field::text('External id')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $external = $this->text($config, 'external_id');
        $query = $external !== null ? ['external_id' => $external] : ['name' => $this->requiredString($config, 'name', 'The name')];

        return $this->listing($run, $config, $external !== null ? '/organizations/search' : '/organizations/autocomplete', 'organizations', $query, $this->shapeOrganization(...), 50);
    }
}
