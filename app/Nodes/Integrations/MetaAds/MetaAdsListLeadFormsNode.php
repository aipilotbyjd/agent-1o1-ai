<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsListLeadFormsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_lead_forms';
    }

    public function name(): string
    {
        return 'Meta Ads: List Lead Forms';
    }

    public function description(): string
    {
        return 'Lists the lead forms of a Page.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['page_id'],
            'properties' => [
                ...$this->credentialFields(),
                'page_id' => Field::dynamic('Page', 'meta_ads.pages'),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $page = $this->nodeId($config, 'page_id', 'The Page');

        return $this->listResult($this->graph($run, $config, 'GET', "/{$page}/leadgen_forms", ['fields' => self::FORM_FIELDS, ...$this->paging($config)], $this->pageToken($run, $config, $page)), 'forms');
    }
}
