<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloUpdateSequenceContactStatusNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_update_sequence_contact_status';
    }

    public function name(): string
    {
        return 'Apollo: Update Contact Status in Sequence';
    }

    public function description(): string
    {
        return 'Marks contacts as finished in sequences, takes them out, or stops them.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['sequence_ids', 'contact_ids', 'mode'],
            'properties' => [
                ...$this->credentialFields(),
                'sequence_ids' => Field::list('Sequence ids'),
                'contact_ids' => Field::list('Contact ids'),
                'mode' => Field::select('Do', ['mark_as_finished' => 'Mark as finished', 'remove' => 'Remove from the sequence', 'stop' => 'Stop'], null, 'stop'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = ['emailer_campaign_ids' => $this->requiredList($config, 'sequence_ids', 'The sequence ids'), 'contact_ids' => $this->requiredList($config, 'contact_ids', 'The contact ids'), 'mode' => $this->requiredString($config, 'mode', 'What to do')];

        return $this->apollo($run, $config, 'POST', '/emailer_campaigns/remove_or_stop_contact_ids', $query);
    }
}
