<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloUpdateSequenceNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_update_sequence';
    }

    public function name(): string
    {
        return 'Apollo: Update Sequence';
    }

    public function description(): string
    {
        return 'Changes the name, schedule, limits or steps of a sequence. Use Activate Sequence to start it.';
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
                'name' => Field::text('Name'),
                'emailer_schedule_id' => Field::advanced(Field::dynamic('Schedule', 'apollo.email_schedules')),
                'user_id' => Field::advanced(Field::dynamic('Owner', 'apollo.users')),
                'label_names' => Field::advanced(Field::list('Labels')),
                'max_emails_per_day' => Field::advanced(Field::integer('Max emails per day')),
                'emailer_steps' => Field::advanced(Field::json('Steps', 'The steps in order, each with type, wait_time, wait_mode and emailer_touches, as in Apollo\'s API.', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'PUT', '/sequences/'.$this->recordId($config, 'sequence_id', 'The sequence'), [], $this->only($config, ['name', 'emailer_schedule_id', 'user_id', 'label_names', 'max_emails_per_day', 'emailer_steps']));

        return ['sequence' => $body['emailer_campaign'] ?? $body, 'steps' => $body['emailer_steps'] ?? []];
    }
}
