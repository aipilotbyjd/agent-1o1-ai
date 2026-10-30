<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsDomainRatingFreeNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_domain_rating_free';
    }

    public function name(): string
    {
        return 'Ahrefs: Domain Rating (Free)';
    }

    public function description(): string
    {
        return 'The Domain Rating of a domain, without using API units.';
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
                'version' => Field::advanced(Field::select('Version', ['dr3', 'dr4'], 'The Domain Rating version.', 'dr3')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/public/domain-rating-free',
            'items' => 'domain_rating',
            'query' => ['target', 'version'],
        ];
    }
}
