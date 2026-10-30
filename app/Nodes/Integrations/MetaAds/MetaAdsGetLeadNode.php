<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetLeadNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_lead';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Lead';
    }

    public function description(): string
    {
        return 'Reads one lead by its id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['lead_id'],
            'properties' => [
                ...$this->credentialFields(),
                'lead_id' => Field::text('Lead id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['lead' => $this->shapeLead($this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'lead_id', 'The lead id'), ['fields' => self::LEAD_FIELDS]))];
    }
}
