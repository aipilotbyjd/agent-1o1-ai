<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsSocialCreatePostNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_social_create_post';
    }

    public function name(): string
    {
        return 'Ahrefs: Create Social Media Post';
    }

    public function description(): string
    {
        return 'Publishes or schedules a post on social media channels.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel_ids', 'text_content', 'timing'],
            'properties' => [
                ...$this->credentialFields(),
                'channel_ids' => Field::list('Channel ids', 'Non-empty list of channel IDs to publish the post to.'),
                'text_content' => Field::text('Text content', 'Text content of the post.'),
                'timing' => Field::select('Timing', ['publish_now', 'scheduled', 'draft'], 'When to publish the post.', null),
                'scheduled_at' => Field::advanced(Field::text('Scheduled at', 'Timestamp for scheduled posts (required when timing is scheduled)')),
                'auto_comment' => Field::advanced(Field::text('Auto comment', 'Auto-comment text posted after publishing.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'POST',
            'path' => '/social-media/post',
            'items' => 'post',
            'body' => ['channel_ids', 'text_content', 'timing', 'scheduled_at', 'auto_comment'],
            'lists' => ['channel_ids'],
        ];
    }
}
