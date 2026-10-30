<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateForumCommentNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_forum_comment';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Forum Comment';
    }

    public function description(): string
    {
        return 'Changes a forum comment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['comment_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'comment_id' => Field::text('Comment id'),
                'body' => Field::textarea('Comment', 'HTML is kept.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'comment_id', 'The comment');

        return $this->freshdesk($run, $config, 'PUT', "/discussions/comments/{$id}", [], ['body' => $this->requiredString($config, 'body', 'The comment')]);
    }
}
