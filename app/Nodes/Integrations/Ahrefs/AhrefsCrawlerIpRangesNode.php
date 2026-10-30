<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;

class AhrefsCrawlerIpRangesNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_crawler_ip_ranges';
    }

    public function name(): string
    {
        return 'Ahrefs: Crawler IP Ranges';
    }

    public function description(): string
    {
        return 'The IP ranges of Ahrefs\' crawler.';
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
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/public/crawler-ip-ranges',
            'items' => 'prefixes',
        ];
    }
}
