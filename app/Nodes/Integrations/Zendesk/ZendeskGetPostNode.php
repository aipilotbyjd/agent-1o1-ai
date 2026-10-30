<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetPostNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_post';
    }

    public function name(): string
    {
        return 'Zendesk: Get Post';
    }

    public function description(): string
    {
        return 'Reads a community post.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['post_id'],
            'properties' => [
                ...$this->credentialFields(),
                'post_id' => Field::text('Post id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'post_id', 'The post');

        return $this->findOne($run, $config, "/community/posts/{$id}", 'post', $id);
    }
}
