<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateForumCommentNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_forum_comment';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Forum Comment';
    }

    public function description(): string
    {
        return 'Posts a comment to a forum topic.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['topic_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'topic_id' => Field::text('Topic id'),
                'body' => Field::textarea('Comment', 'HTML is kept.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'topic_id', 'The topic');

        return $this->freshdesk($run, $config, 'POST', "/discussions/topics/{$id}/comments", [], ['body' => $this->requiredString($config, 'body', 'The comment')]);
    }
}
