<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUnfollowForumTopicNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_unfollow_forum_topic';
    }

    public function name(): string
    {
        return 'Freshdesk: Unfollow Forum Topic';
    }

    public function description(): string
    {
        return 'Unsubscribes a person from updates on a topic.';
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
                'user_id' => Field::advanced(Field::text('User id')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'topic_id', 'The topic');
        $this->freshdesk($run, $config, 'DELETE', "/discussions/topics/{$id}/follow", ['user_id' => $this->optionalId($config, 'user_id', 'The user')]);

        return ['following' => false, 'topic_id' => (int) $id];
    }
}
