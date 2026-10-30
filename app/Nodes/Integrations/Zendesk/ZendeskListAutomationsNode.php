<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskListAutomationsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_list_automations';
    }

    public function name(): string
    {
        return 'Zendesk: List Automations';
    }

    public function description(): string
    {
        return 'Lists the account\'s automations, the rules that run on time.';
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
        return $this->listing($run, $config, '/automations', 'automations');
    }
}
