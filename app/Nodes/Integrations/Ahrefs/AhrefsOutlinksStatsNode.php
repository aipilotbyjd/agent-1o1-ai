<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsOutlinksStatsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_outlinks_stats';
    }

    public function name(): string
    {
        return 'Ahrefs: Outlinks Stats';
    }

    public function description(): string
    {
        return 'How many outgoing links and linked domains a domain or URL has.';
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
                'mode' => Field::advanced(Field::select('Scope', ['exact', 'prefix', 'domain', 'subdomains'], 'The scope of the target.', 'subdomains')),
                'protocol' => Field::advanced(Field::select('Protocol', ['both', 'http', 'https'], 'The protocol of your target.', 'both')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-explorer/outlinks-stats',
            'items' => 'metrics',
            'query' => ['target', 'mode', 'protocol'],
        ];
    }
}
