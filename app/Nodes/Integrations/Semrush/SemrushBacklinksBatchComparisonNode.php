<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushBacklinksBatchComparisonNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_backlinks_batch_comparison';
    }

    public function name(): string
    {
        return 'Semrush: Backlinks Batch Comparison';
    }

    public function description(): string
    {
        return 'Compares the backlink profiles of up to 200 targets.';
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
                'columns' => $this->columnsField(['target', 'target_type', 'ascore', 'backlinks_num', 'domains_num', 'ips_num', 'follows_num', 'nofollows_num', 'texts_num', 'images_num', 'forms_num', 'frames_num']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $targets = $this->requiredList($config, 'targets', 'The targets');
        $type = (string) ($config['target_type'] ?? 'root_domain');

        return $this->seoReport($run, $config, 'backlinks_comparison', 'target,target_type,ascore,backlinks_num,domains_num,ips_num,follows_num,nofollows_num,texts_num,images_num,forms_num,frames_num', ['targets' => $targets, 'target_types' => array_fill(0, count($targets), $type), 'target_type' => null], '/analytics/v1/');
    }
}
