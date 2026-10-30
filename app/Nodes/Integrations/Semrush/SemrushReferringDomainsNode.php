<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushReferringDomainsNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_referring_domains';
    }

    public function name(): string
    {
        return 'Semrush: Referring Domains';
    }

    public function description(): string
    {
        return 'The domains linking to a domain or URL.';
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
                'sort' => $this->sortField(['domain_ascore_asc', 'domain_ascore_desc', 'backlinks_num_asc', 'backlinks_num_desc', 'last_seen_asc', 'last_seen_desc', 'first_seen_asc', 'first_seen_desc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['domain_ascore', 'domain_score', 'domain', 'backlinks_num', 'domain_trust_score', 'ip', 'country', 'first_seen', 'last_seen']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'backlinks_refdomains', 'domain_ascore,domain,backlinks_num,ip,country,first_seen,last_seen', ['target' => $this->requiredString($config, 'target', 'The target')], '/analytics/v1/');
    }
}
