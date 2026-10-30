<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsListLeadsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_leads';
    }

    public function name(): string
    {
        return 'Meta Ads: List Leads';
    }

    public function description(): string
    {
        return 'Reads the leads of a form, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['form_id'],
            'properties' => [
                ...$this->credentialFields(),
                'page_id' => Field::dynamic('Page', 'meta_ads.pages'),
                'form_id' => Field::dynamic('Lead form', 'meta_ads.lead_forms', null, null, ['page_id']),
                'since' => Field::advanced(Field::datetime('Since')),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $filtering = filled($config['since'] ?? null) ? [['field' => 'time_created', 'operator' => 'GREATER_THAN', 'value' => (int) strtotime((string) $config['since'])]] : null;

        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'form_id', 'The form').'/leads', ['fields' => self::LEAD_FIELDS, 'filtering' => $filtering, ...$this->paging($config)]), 'leads', $this->shapeLead(...));
    }
}
