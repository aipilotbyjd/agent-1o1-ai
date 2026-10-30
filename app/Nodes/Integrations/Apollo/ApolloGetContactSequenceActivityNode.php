<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloGetContactSequenceActivityNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_contact_sequence_activity';
    }

    public function name(): string
    {
        return 'Apollo: Get Contact Sequence Activity';
    }

    public function description(): string
    {
        return 'Reads what a contact has been through in sequences.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::dynamic('Contact', 'apollo.contacts'),
                'sequence_id' => Field::dynamic('Sequence', 'apollo.sequences'),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = ['contact_id' => $this->recordId($config, 'contact_id', 'The contact'), 'sequence_id' => filled($config['sequence_id'] ?? null) ? $this->recordId($config, 'sequence_id', 'The sequence') : null, 'per_page' => (int) ($config['limit'] ?? 25)];

        return $this->apollo($run, $config, 'POST', '/emailer_campaigns/activity_feed', [], $body);
    }
}
