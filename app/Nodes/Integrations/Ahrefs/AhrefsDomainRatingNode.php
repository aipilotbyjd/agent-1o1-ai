<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsDomainRatingNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_domain_rating';
    }

    public function name(): string
    {
        return 'Ahrefs: Domain Rating';
    }

    public function description(): string
    {
        return 'The Domain Rating and Ahrefs Rank of a domain.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'The target of the search: a domain or a URL.'),
                'date' => Field::text('Date', 'A date to report metrics on in YYYY-MM-DD format.'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-explorer/domain-rating',
            'items' => 'domain_rating',
            'query' => ['target', 'date'],
            'dated' => true,
        ];
    }
}
