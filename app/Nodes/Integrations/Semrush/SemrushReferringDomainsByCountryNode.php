<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushReferringDomainsByCountryNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_referring_domains_by_country';
    }

    public function name(): string
    {
        return 'Semrush: Referring Domains by Country';
    }

    public function description(): string
    {
        return 'Referring domains and backlinks by country.';
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
                'sort' => $this->sortField(['domains_num_asc', 'domains_num_desc', 'backlinks_num_asc', 'backlinks_num_desc']),
                'columns' => $this->columnsField(['country', 'domains_num', 'backlinks_num']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'backlinks_geo', 'country,domains_num,backlinks_num', ['target' => $this->requiredString($config, 'target', 'The target')], '/analytics/v1/');
    }
}
