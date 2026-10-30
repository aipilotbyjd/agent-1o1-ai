<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetInsightsReportNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_insights_report';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Insights Report';
    }

    public function description(): string
    {
        return 'Reads how far a background insights report is, and its rows once it is finished.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['report_run_id'],
            'properties' => [
                ...$this->credentialFields(),
                'report_run_id' => Field::text('Report id'),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'report_run_id', 'The report id');
        $report = $this->graph($run, $config, 'GET', "/{$id}", ['fields' => 'id,async_status,async_percent_completion,error_code,error_message']);
        $finished = ($report['async_status'] ?? null) === 'Job Completed';

        return ['status' => $report['async_status'] ?? null, 'percent' => $report['async_percent_completion'] ?? null, 'finished' => $finished, 'error' => $report['error_message'] ?? null, ...($finished ? $this->listResult($this->graph($run, $config, 'GET', "/{$id}/insights", $this->paging($config)), 'rows') : ['rows' => [], 'count' => 0, 'next_cursor' => null])];
    }
}
