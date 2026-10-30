<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteThreadMessageNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_thread_message';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Thread Message';
    }

    public function description(): string
    {
        return 'Deletes a collaboration thread message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::text('Message id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'message_id', 'The message');

        return $this->destroy($run, $config, "/collaboration/messages/{$id}", $id);
    }
}
