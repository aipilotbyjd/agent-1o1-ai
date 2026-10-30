<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;

class AhrefsCrawlerIpsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_crawler_ips';
    }

    public function name(): string
    {
        return 'Ahrefs: Crawler IP Addresses';
    }

    public function description(): string
    {
        return 'The IP addresses of Ahrefs\' crawler.';
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
            'path' => '/public/crawler-ips',
            'items' => 'ips',
        ];
    }
}
