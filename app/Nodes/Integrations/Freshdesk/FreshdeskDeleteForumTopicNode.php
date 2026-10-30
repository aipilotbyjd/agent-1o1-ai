<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteForumTopicNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_forum_topic';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Forum Topic';
    }

    public function description(): string
    {
        return 'Deletes a forum topic.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        return $this->destroy($run, $config, "/discussions/topics/{$id}", $id);
    }
}
