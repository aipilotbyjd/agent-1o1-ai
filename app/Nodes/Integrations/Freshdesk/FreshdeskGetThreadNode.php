<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetThreadNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_thread';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Thread';
    }

    public function description(): string
    {
        return 'Reads a collaboration thread.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['thread_id'],
            'properties' => [
                ...$this->credentialFields(),
                'thread_id' => Field::text('Thread id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'thread_id', 'The thread');

        return $this->findOne($run, $config, "/collaboration/threads/{$id}", $id);
    }
}
