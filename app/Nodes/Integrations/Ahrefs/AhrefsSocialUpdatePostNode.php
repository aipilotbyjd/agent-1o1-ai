<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsSocialUpdatePostNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_social_update_post';
    }

    public function name(): string
    {
        return 'Ahrefs: Update Social Media Post';
    }

    public function description(): string
    {
        return 'Changes a post that has not been published yet.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['post_id'],
            'properties' => [
                ...$this->credentialFields(),
                'post_id' => Field::integer('Post id', 'Unique identifier for the post.'),
                'channel_ids' => Field::advanced(Field::list('Channel ids', 'Non-empty list of channel IDs to publish the post to.')),
                'text_content' => Field::advanced(Field::text('Text content', 'Text content of the post.')),
                'timing' => Field::advanced(Field::select('Timing', ['publish_now', 'scheduled', 'draft'], 'When to publish the post.', null)),
                'scheduled_at' => Field::advanced(Field::text('Scheduled at', 'Timestamp for scheduled posts (required when timing is scheduled)')),
                'auto_comment' => Field::advanced(Field::text('Auto comment', 'Auto-comment text posted after publishing.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'PATCH',
            'path' => '/social-media/post',
            'items' => 'post',
            'body' => ['post_id', 'channel_ids', 'text_content', 'timing', 'scheduled_at', 'auto_comment'],
            'lists' => ['channel_ids'],
            'integers' => ['post_id'],
        ];
    }
}
