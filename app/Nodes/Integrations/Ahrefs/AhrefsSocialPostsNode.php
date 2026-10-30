<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsSocialPostsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_social_posts';
    }

    public function name(): string
    {
        return 'Ahrefs: Social Media Posts';
    }

    public function description(): string
    {
        return 'Lists social media posts by status.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['status'],
            'properties' => [
                ...$this->credentialFields(),
                'status' => Field::select('Status', ['published', 'scheduled', 'draft', 'failed', 'deleted'], 'The publishing lifecycle stage of the post.', null),
                'limit' => Field::integer('Limit', 'The most rows to return. Ahrefs charges per row.', 100, 1, 100000),
                'channel_ids' => Field::advanced(Field::text('Channel ids', 'Comma-separated list of channel IDs to filter by.')),
                'author_ids' => Field::advanced(Field::text('Author ids', 'Comma-separated list of author user IDs to filter by.')),
                'search_query' => Field::advanced(Field::text('Search query', 'Text search query to filter posts by content.')),
                'date_from' => Field::text('From', 'The start date of the historical period in YYYY-MM-DD format.'),
                'date_to' => Field::text('To', 'The end date of the historical period in YYYY-MM-DD format.'),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as traffic:desc.', 'traffic:desc')),
                'order_direction' => Field::advanced(Field::select('Order direction', ['asc', 'desc'], 'The order in which results are returned, ascending or descending.', null)),
                'offset' => Field::advanced(Field::integer('Offset', 'The number of results to skip.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/social-media/posts',
            'items' => 'posts',
            'query' => ['status', 'limit', 'channel_ids', 'author_ids', 'search_query', 'date_from', 'date_to', 'order_by', 'order_direction', 'offset'],
            'limited' => true,
        ];
    }
}
