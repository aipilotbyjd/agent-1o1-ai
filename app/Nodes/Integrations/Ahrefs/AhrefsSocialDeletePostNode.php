<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsSocialDeletePostNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_social_delete_post';
    }

    public function name(): string
    {
        return 'Ahrefs: Delete Social Media Post';
    }

    public function description(): string
    {
        return 'Deletes a social media post.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['post_id'],
            'properties' => [
                ...$this->credentialFields(),
                'post_id' => Field::integer('Post id', 'Unique identifier for the post.'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'DELETE',
            'path' => '/social-media/post',
            'items' => 'post',
            'body' => ['post_id'],
            'integers' => ['post_id'],
        ];
    }
}
