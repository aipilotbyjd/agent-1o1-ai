<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateThreadNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_thread';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Thread';
    }

    public function description(): string
    {
        return 'Changes a collaboration thread\'s title or description.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['thread_id'],
            'properties' => [
                ...$this->credentialFields(),
                'thread_id' => Field::text('Thread id'),
                'title' => Field::advanced(Field::text('Title')),
                'description' => Field::advanced(Field::textarea('Description')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'thread_id', 'The thread');
        $body = $this->present(['title' => $this->text($config, 'title'), 'description' => $this->text($config, 'description')]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->freshdesk($run, $config, 'PUT', "/collaboration/threads/{$id}", [], $body);
    }
}
