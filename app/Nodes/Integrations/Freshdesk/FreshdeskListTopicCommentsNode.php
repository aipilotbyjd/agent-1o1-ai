<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskListTopicCommentsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_topic_comments';
    }

    public function name(): string
    {
        return 'Freshdesk: List Topic Comments';
    }

    public function description(): string
    {
        return 'Lists the comments on a forum topic.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'topic_id', 'The topic');

        return $this->listing($run, $config, "/discussions/topics/{$id}/comments", 'comments');
    }
}
