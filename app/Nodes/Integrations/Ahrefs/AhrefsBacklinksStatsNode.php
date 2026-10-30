<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsBacklinksStatsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_backlinks_stats';
    }

    public function name(): string
    {
        return 'Ahrefs: Backlinks Stats';
    }

    public function description(): string
    {
        return 'How many live and all-time backlinks and referring domains a domain or URL has.';
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
                'mode' => Field::advanced(Field::select('Scope', ['exact', 'prefix', 'domain', 'subdomains'], 'The scope of the search based on the target you entered.', 'subdomains')),
                'portfolio_id' => Field::advanced(Field::integer('Portfolio id', 'The unique identifier of the portfolio.')),
                'protocol' => Field::advanced(Field::select('Protocol', ['both', 'http', 'https'], 'The protocol of your target.', 'both')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-explorer/backlinks-stats',
            'items' => 'metrics',
            'query' => ['target', 'date', 'mode', 'portfolio_id', 'protocol'],
            'dated' => true,
        ];
    }
}
