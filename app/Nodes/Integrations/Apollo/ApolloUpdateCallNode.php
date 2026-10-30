<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloUpdateCallNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_update_call';
    }

    public function name(): string
    {
        return 'Apollo: Update Call';
    }

    public function description(): string
    {
        return 'Changes a call record.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['call_id'],
            'properties' => [
                ...$this->credentialFields(),
                'call_id' => Field::text('Call id'),
                'contact_id' => Field::dynamic('Contact', 'apollo.contacts'),
                'account_id' => Field::dynamic('Account', 'apollo.accounts'),
                'to_number' => Field::text('To number'),
                'from_number' => Field::text('From number'),
                'status' => Field::text('Status', 'Like completed or no_answer.'),
                'start_time' => Field::advanced(Field::datetime('Started at')),
                'end_time' => Field::advanced(Field::datetime('Ended at')),
                'duration' => Field::advanced(Field::integer('Duration (seconds)')),
                'note' => Field::advanced(Field::textarea('Note')),
                'phone_call_purpose_id' => Field::advanced(Field::text('Purpose id')),
                'phone_call_outcome_id' => Field::advanced(Field::text('Outcome id')),
                'user_ids' => Field::advanced(Field::list('User ids')),
                'logged' => Field::advanced(Field::boolean('Log as an activity', null, true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'PUT', '/phone_calls/'.$this->recordId($config, 'call_id', 'The call id'), ['user_id' => $this->stringList($config, 'user_ids'), ...$this->only($config, ['contact_id', 'account_id', 'to_number', 'from_number', 'status', 'start_time', 'end_time', 'duration', 'note', 'phone_call_purpose_id', 'phone_call_outcome_id']), 'logged' => $config['logged'] ?? null]);

        return ['call' => $body['phone_call'] ?? $body];
    }
}
