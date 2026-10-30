<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushIndexedPagesNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_indexed_pages';
    }

    public function name(): string
    {
        return 'Semrush: Indexed Pages';
    }

    public function description(): string
    {
        return 'The pages of a domain with backlinks.';
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
                'sort' => $this->sortField(['backlinks_num_asc', 'backlinks_num_desc', 'domains_num_asc', 'domains_num_desc', 'last_seen_asc', 'last_seen_desc']),
                'columns' => $this->columnsField(['source_url', 'source_title', 'response_code', 'backlinks_num', 'domains_num', 'last_seen', 'external_num', 'internal_num']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'backlinks_pages', 'source_url,source_title,response_code,backlinks_num,domains_num,last_seen,external_num,internal_num', ['target' => $this->requiredString($config, 'target', 'The target')], '/analytics/v1/');
    }
}
