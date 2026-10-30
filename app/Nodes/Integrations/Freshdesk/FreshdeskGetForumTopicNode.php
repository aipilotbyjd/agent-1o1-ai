<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetForumTopicNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_forum_topic';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Forum Topic';
    }

    public function description(): string
    {
        return 'Reads a forum topic.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['topic_id'],
            'properties' => [
                ...$this->credentialFields(),
                'topic_id' => Field::text('Topic id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'topic_id', 'The topic');

        return $this->findOne($run, $config, "/discussions/topics/{$id}", $id);
    }
}
