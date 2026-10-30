<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateThreadMessageNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_thread_message';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Thread Message';
    }

    public function description(): string
    {
        return 'Posts a message to a collaboration thread, emailing the people named.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['thread_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'thread_id' => Field::text('Thread id'),
                'body' => Field::textarea('Message', 'HTML is kept.'),
                'to' => Field::advanced(Field::emails('To')),
                'cc' => Field::advanced(Field::emails('CC')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->freshdesk($run, $config, 'POST', '/collaboration/messages', [], $this->present(['thread_id' => $this->numericId($config, 'thread_id', 'The thread'), 'body' => $this->requiredString($config, 'body', 'The message'), 'participants' => filled($config['to'] ?? null) ? ['email' => $this->present(['to' => $this->words($config, 'to'), 'cc' => $this->words($config, 'cc')])] : null]));
    }
}
