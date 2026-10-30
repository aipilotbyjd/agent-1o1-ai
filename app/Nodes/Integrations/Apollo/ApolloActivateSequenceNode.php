<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloActivateSequenceNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_activate_sequence';
    }

    public function name(): string
    {
        return 'Apollo: Activate Sequence';
    }

    public function description(): string
    {
        return 'Starts a sequence, so it begins emailing the contacts in it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['sequence_id'],
            'properties' => [
                ...$this->credentialFields(),
                'sequence_id' => Field::dynamic('Sequence', 'apollo.sequences'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'POST', '/emailer_campaigns/'.$this->recordId($config, 'sequence_id', 'The sequence').'/approve');

        return ['sequence' => $body['emailer_campaign'] ?? $body];
    }
}
