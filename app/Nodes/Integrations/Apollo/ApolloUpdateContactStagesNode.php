<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloUpdateContactStagesNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_update_contact_stages';
    }

    public function name(): string
    {
        return 'Apollo: Update Contact Stage';
    }

    public function description(): string
    {
        return 'Moves one or more contacts to a stage.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_ids', 'contact_stage_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_ids' => Field::list('Contact ids'),
                'contact_stage_id' => Field::dynamic('Contact stage', 'apollo.contact_stages'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = ['contact_ids' => $this->requiredList($config, 'contact_ids', 'The contact ids'), 'contact_stage_id' => $this->requiredString($config, 'contact_stage_id', 'The stage')];

        return $this->listResult($this->apollo($run, $config, 'POST', '/contacts/update_stages', $query), 'contacts', $this->shapeContact(...));
    }
}
