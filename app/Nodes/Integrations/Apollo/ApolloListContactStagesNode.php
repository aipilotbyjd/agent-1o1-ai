<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApolloListContactStagesNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_list_contact_stages';
    }

    public function name(): string
    {
        return 'Apollo: List Contact Stages';
    }

    public function description(): string
    {
        return 'Lists the stages a contact can be in.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->apollo($run, $config, 'GET', '/contact_stages'), 'contact_stages');
    }
}
