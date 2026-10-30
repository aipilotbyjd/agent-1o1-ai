<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsSocialActivityHistoryNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_social_activity_history';
    }

    public function name(): string
    {
        return 'Ahrefs: Social Media Post Activity';
    }

    public function description(): string
    {
        return 'The history of a social media post.';
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
                'post_id' => Field::integer('Post id', 'Unique identifier for the post.'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/social-media/activity-history',
            'items' => 'activities',
            'query' => ['post_id'],
        ];
    }
}
