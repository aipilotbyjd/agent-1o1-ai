<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsSocialPostMetricsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_social_post_metrics';
    }

    public function name(): string
    {
        return 'Ahrefs: Social Media Post Metrics';
    }

    public function description(): string
    {
        return 'The views and likes of a published post over time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['external_post_id', 'channel_id', 'date_from'],
            'properties' => [
                ...$this->credentialFields(),
                'external_post_id' => Field::text('External post id', 'Platform-specific post identifier.'),
                'channel_id' => Field::text('Channel id', 'Unique identifier for the social media channel.'),
                'date_from' => Field::text('From', 'The start date of the historical period in YYYY-MM-DD format.'),
                'date_to' => Field::text('To', 'The end date of the historical period in YYYY-MM-DD format.'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/social-media/post-metrics',
            'items' => 'metrics',
            'query' => ['external_post_id', 'channel_id', 'date_from', 'date_to'],
        ];
    }
}
