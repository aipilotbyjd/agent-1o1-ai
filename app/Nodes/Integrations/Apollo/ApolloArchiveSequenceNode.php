<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloArchiveSequenceNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_archive_sequence';
    }

    public function name(): string
    {
        return 'Apollo: Archive Sequence';
    }

    public function description(): string
    {
        return 'Archives a sequence, which stops it and hides it from the active list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        $body = $this->apollo($run, $config, 'POST', '/emailer_campaigns/'.$this->recordId($config, 'sequence_id', 'The sequence').'/archive');

        return ['sequence' => $body['emailer_campaign'] ?? $body];
    }
}
