<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateThreadMessageNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_thread_message';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Thread Message';
    }

    public function description(): string
    {
        return 'Changes a collaboration thread message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::text('Message id'),
                'body' => Field::textarea('Message', 'HTML is kept.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'message_id', 'The message');

        return $this->freshdesk($run, $config, 'PUT', "/collaboration/messages/{$id}", [], ['body' => $this->requiredString($config, 'body', 'The message')]);
    }
}
