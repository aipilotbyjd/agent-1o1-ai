<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskListAgentsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_agents';
    }

    public function name(): string
    {
        return 'Freshdesk: List Agents';
    }

    public function description(): string
    {
        return 'Lists the account\'s agents.';
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
                'email' => Field::advanced(Field::text('Email')),
                'state' => Field::advanced(Field::select('State', ['fulltime' => 'Full time', 'occasional' => 'Occasional'])),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/agents', 'agents', ['email' => $this->text($config, 'email'), 'state' => $this->text($config, 'state')]);
    }
}
