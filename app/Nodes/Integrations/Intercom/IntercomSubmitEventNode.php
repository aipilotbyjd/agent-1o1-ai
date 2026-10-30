<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class IntercomSubmitEventNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_submit_event';
    }

    public function name(): string
    {
        return 'Intercom: Submit Data Event';
    }

    public function description(): string
    {
        return 'Records something a contact did, such as "invited-friend".';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['event_name'],
            'properties' => [
                ...$this->credentialFields(),
                'event_name' => Field::text('Event name', null, 'invited-friend'),
                'user_id' => Field::text('External user id', 'Or use the contact id or email.'),
                'contact_id' => Field::advanced(Field::text('Contact id')),
                'email' => Field::advanced(Field::text('Email')),
                'created_at' => Field::advanced(Field::datetime('When', 'Empty is now.')),
                'metadata' => Field::advanced(Field::json('Details (JSON)', 'e.g. {"plan": "gold"}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->present(['event_name' => $this->requiredString($config, 'event_name', 'The event name'), 'created_at' => filled($config['created_at'] ?? null) ? Carbon::parse((string) $config['created_at'])->getTimestamp() : now()->getTimestamp(), 'user_id' => $this->text($config, 'user_id'), 'id' => $this->text($config, 'contact_id'), 'email' => filled($config['email'] ?? null) ? $this->email($config) : null, 'metadata' => $this->jsonObject($config, 'metadata', 'The details') ?: null]);
        if (! isset($body['user_id']) && ! isset($body['id']) && ! isset($body['email'])) {
            throw new \InvalidArgumentException('Say who did it: a user id, a contact id or an email.');
        }
        $this->intercom($run, $config, 'POST', '/events', [], $body);

        return ['submitted' => true, 'event_name' => $body['event_name']];
    }
}
