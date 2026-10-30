<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskSearchContactsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_search_contacts';
    }

    public function name(): string
    {
        return 'Freshdesk: Search Contacts';
    }

    public function description(): string
    {
        return 'Finds contacts with Freshdesk\'s filter language, e.g. active:true.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Filter', 'Field names as in List Contact Fields. Text values in single quotes.', 'active:true'),
                'limit' => Field::integer('Max results', null, 30, 1, 300),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->searchAll($run, $config, '/search/contacts', $this->requiredString($config, 'query', 'The filter'), $this->limit($config, 30, 300));

        return ['contacts' => array_map($this->shapeContact(...), $rows), 'count' => count($rows)];
    }
}
