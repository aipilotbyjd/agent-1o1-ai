<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsMetricsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_metrics';
    }

    public function name(): string
    {
        return 'Ahrefs: Metrics';
    }

    public function description(): string
    {
        return 'Organic and paid traffic, keywords and cost of a domain or URL.';
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
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'mode' => Field::advanced(Field::select('Scope', ['exact', 'prefix', 'domain', 'subdomains'], 'The scope of the target.', 'subdomains')),
                'portfolio_id' => Field::advanced(Field::integer('Portfolio id', 'The unique identifier of the portfolio.')),
                'protocol' => Field::advanced(Field::select('Protocol', ['both', 'http', 'https'], 'The protocol of your target.', 'both')),
                'volume_mode' => Field::advanced(Field::select('Volume mode', ['monthly', 'average'], 'The search volume calculation mode: monthly or average.', 'monthly')),
                'traffic_mode' => Field::advanced(Field::select('Traffic mode', ['static', 'adaptive'], 'The organic traffic calculation mode: static or adaptive.', 'adaptive')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-explorer/metrics',
            'items' => 'metrics',
            'query' => ['target', 'date', 'country', 'mode', 'portfolio_id', 'protocol', 'volume_mode', 'traffic_mode'],
            'dated' => true,
        ];
    }
}
