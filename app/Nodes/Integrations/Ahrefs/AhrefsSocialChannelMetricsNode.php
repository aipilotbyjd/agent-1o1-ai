<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsSocialChannelMetricsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_social_channel_metrics';
    }

    public function name(): string
    {
        return 'Ahrefs: Social Media Channel Metrics';
    }

    public function description(): string
    {
        return 'The followers of a channel over time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel_id', 'date_from'],
            'properties' => [
                ...$this->credentialFields(),
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
            'path' => '/social-media/channel-metrics',
            'items' => 'metrics',
            'query' => ['channel_id', 'date_from', 'date_to'],
        ];
    }
}
