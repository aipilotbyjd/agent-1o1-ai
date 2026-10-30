<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsPagesByTrafficNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_pages_by_traffic';
    }

    public function name(): string
    {
        return 'Ahrefs: Pages by Traffic';
    }

    public function description(): string
    {
        return 'How many pages of a domain or URL fall in each traffic range.';
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
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'portfolio_id' => Field::advanced(Field::integer('Portfolio id', 'The unique identifier of the portfolio.')),
                'protocol' => Field::advanced(Field::select('Protocol', ['both', 'http', 'https'], 'The protocol of your target.', 'both')),
                'mode' => Field::advanced(Field::select('Scope', ['exact', 'prefix', 'domain', 'subdomains'], 'The scope of the target.', 'subdomains')),
                'volume_mode' => Field::advanced(Field::select('Volume mode', ['monthly', 'average'], 'The search volume calculation mode: monthly or average.', 'monthly')),
                'traffic_mode' => Field::advanced(Field::select('Traffic mode', ['static', 'adaptive'], 'The organic traffic calculation mode: static or adaptive.', 'adaptive')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-explorer/pages-by-traffic',
            'items' => 'pages',
            'query' => ['target', 'country', 'portfolio_id', 'protocol', 'mode', 'volume_mode', 'traffic_mode'],
        ];
    }
}
