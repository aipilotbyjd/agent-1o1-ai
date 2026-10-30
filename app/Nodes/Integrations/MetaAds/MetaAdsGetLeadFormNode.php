<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetLeadFormNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_lead_form';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Lead Form';
    }

    public function description(): string
    {
        return 'Reads one lead form with its questions.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['page_id', 'form_id'],
            'properties' => [
                ...$this->credentialFields(),
                'page_id' => Field::dynamic('Page', 'meta_ads.pages'),
                'form_id' => Field::dynamic('Lead form', 'meta_ads.lead_forms', null, null, ['page_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['form' => $this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'form_id', 'The form'), ['fields' => self::FORM_FIELDS], $this->pageToken($run, $config, $this->nodeId($config, 'page_id', 'The Page')))];
    }
}
