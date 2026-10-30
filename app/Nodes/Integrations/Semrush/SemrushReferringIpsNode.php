<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushReferringIpsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_referring_ips';
    }

    public function name(): string
    {
        return 'Semrush: Referring IPs';
    }

    public function description(): string
    {
        return 'The IP addresses linking to a domain or URL.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target', 'target_type'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'A root domain, a subdomain or a URL.', 'example.com'),
                'target_type' => $this->targetTypeField(),
                'limit' => $this->limitField(),
                'offset' => $this->offsetField(),
                'sort' => $this->sortField(['domains_num_asc', 'domains_num_desc', 'backlinks_num_asc', 'backlinks_num_desc', 'first_seen_asc', 'first_seen_desc', 'last_seen_asc', 'last_seen_desc']),
                'columns' => $this->columnsField(['ip', 'country', 'domains_num', 'backlinks_num', 'first_seen', 'last_seen']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'backlinks_refips', 'ip,country,domains_num,backlinks_num,first_seen,last_seen', ['target' => $this->requiredString($config, 'target', 'The target')], '/analytics/v1/');
    }
}
