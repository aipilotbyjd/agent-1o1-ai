<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteForumCommentNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_forum_comment';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Forum Comment';
    }

    public function description(): string
    {
        return 'Deletes a forum comment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['comment_id'],
            'properties' => [
                ...$this->credentialFields(),
                'comment_id' => Field::text('Comment id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'comment_id', 'The comment');

        return $this->destroy($run, $config, "/discussions/comments/{$id}", $id);
    }
}
