<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsDomainRatingTopDomainsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_domain_rating_top_domains';
    }

    public function name(): string
    {
        return 'Ahrefs: Top Domains by Domain Rating';
    }

    public function description(): string
    {
        return 'The domains with the highest Domain Rating.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'from' => Field::integer('From', 'Rank position to start from.'),
                'to' => Field::integer('To', 'Rank position to end at.'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/public/domain-rating-top-domains',
            'items' => 'domains',
            'query' => ['from', 'to'],
        ];
    }
}
