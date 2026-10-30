<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskListTriggersNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_list_triggers';
    }

    public function name(): string
    {
        return 'Zendesk: List Business Rule Triggers';
    }

    public function description(): string
    {
        return 'Lists the account\'s triggers, the rules that run when tickets change.';
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
        return $this->listing($run, $config, '/triggers', 'triggers');
    }
}
