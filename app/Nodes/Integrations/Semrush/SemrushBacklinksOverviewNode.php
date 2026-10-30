<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushBacklinksOverviewNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_backlinks_overview';
    }

    public function name(): string
    {
        return 'Semrush: Backlinks Overview';
    }

    public function description(): string
    {
        return 'A summary of the backlinks of a domain or URL: Authority Score, totals, referring domains and IPs.';
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
                'columns' => $this->columnsField(['ascore', 'total', 'domains_num', 'urls_num', 'ips_num', 'ipclassc_num', 'follows_num', 'nofollows_num', 'sponsored_num', 'ugc_num', 'texts_num', 'images_num', 'forms_num', 'frames_num']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'backlinks_overview', 'ascore,total,domains_num,urls_num,ips_num,ipclassc_num,follows_num,nofollows_num,sponsored_num,ugc_num,texts_num,images_num,forms_num,frames_num', ['target' => $this->requiredString($config, 'target', 'The target')], '/analytics/v1/');
    }
}
