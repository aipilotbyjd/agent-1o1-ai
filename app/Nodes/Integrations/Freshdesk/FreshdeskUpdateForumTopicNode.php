<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateForumTopicNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_forum_topic';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Forum Topic';
    }

    public function description(): string
    {
        return 'Changes a forum topic.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['topic_id'],
            'properties' => [
                ...$this->credentialFields(),
                'topic_id' => Field::text('Topic id'),
                'title' => Field::advanced(Field::text('Title')),
                'message' => Field::advanced(Field::textarea('Message', 'HTML is kept.')),
                'sticky' => Field::advanced(Field::boolean('Sticky')),
                'locked' => Field::advanced(Field::boolean('Locked')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'topic_id', 'The topic');
        $body = $this->present(['title' => $this->text($config, 'title'), 'message' => $this->text($config, 'message'), 'sticky' => $this->flag($config, 'sticky'), 'locked' => $this->flag($config, 'locked')]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->freshdesk($run, $config, 'PUT', "/discussions/topics/{$id}", [], $body);
    }
}
