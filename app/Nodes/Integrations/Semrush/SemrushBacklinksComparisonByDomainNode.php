<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushBacklinksComparisonByDomainNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_backlinks_comparison_by_domain';
    }

    public function name(): string
    {
        return 'Semrush: Backlinks Comparison by Referring Domain';
    }

    public function description(): string
    {
        return 'How many backlinks up to five targets get from the same referring domains.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['targets'],
            'properties' => [
                ...$this->credentialFields(),
                'targets' => Field::list('Targets', 'Domains or URLs to compare.', 'example.com'),
                'target_type' => $this->targetTypeField(),
                'limit' => $this->limitField(),
                'offset' => $this->offsetField(),
                'sort' => $this->sortField(['domain_score_desc', 'domain_score_asc', 'matchesnum_desc', 'matchesnum_asc', 'backlinksnum_0_desc', 'backlinksnum_0_asc', 'backlinksnum_1_desc', 'backlinksnum_1_asc', 'backlinksnum_2_desc', 'backlinksnum_2_asc', 'backlinksnum_3_desc', 'backlinksnum_3_asc', 'backlinksnum_4_desc', 'backlinksnum_4_asc', 'backlinksnum_5_desc', 'backlinksnum_5_asc']),
                'filter' => $this->filterField(),
                'columns' => $this->columnsField(['domain', 'domain_ascore', 'domain_score', 'matches_num', 'backlinks_num']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $targets = $this->requiredList($config, 'targets', 'The targets');
        $type = (string) ($config['target_type'] ?? 'root_domain');

        return $this->seoReport($run, $config, 'backlinks_matrix', 'domain,domain_ascore,matches_num,backlinks_num', ['targets' => $targets, 'target_types' => array_fill(0, count($targets), $type), 'target_type' => null], '/analytics/v1/');
    }
}
