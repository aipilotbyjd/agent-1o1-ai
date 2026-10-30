<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsDomainRatingHistoryNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_domain_rating_history';
    }

    public function name(): string
    {
        return 'Ahrefs: Domain Rating History';
    }

    public function description(): string
    {
        return 'The Domain Rating of a domain over time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target', 'date_from'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'The target of the search: a domain or a URL.'),
                'date_from' => Field::text('From', 'The start date of the historical period in YYYY-MM-DD format.'),
                'date_to' => Field::text('To', 'The end date of the historical period in YYYY-MM-DD format.'),
                'history_grouping' => Field::advanced(Field::select('History grouping', ['daily', 'weekly', 'monthly'], 'The time interval used to group historical data.', 'monthly')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-explorer/domain-rating-history',
            'items' => 'domain_ratings',
            'query' => ['target', 'date_from', 'date_to', 'history_grouping'],
        ];
    }
}
