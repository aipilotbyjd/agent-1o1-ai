<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomFindLeadNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_find_lead';
    }

    public function name(): string
    {
        return 'Intercom: Find Lead';
    }

    public function description(): string
    {
        return 'Finds a lead by email, or says there is none.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = (array) ($this->intercom($run, $config, 'POST', '/contacts/search', [], ['query' => $this->allOf([['field' => 'role', 'operator' => '=', 'value' => 'lead'], ['field' => 'email', 'operator' => '=', 'value' => $this->email($config)]])])['data'] ?? []);

        return $found === [] ? ['found' => false] : ['found' => true, ...$this->shapeContact((array) $found[0])];
    }
}
