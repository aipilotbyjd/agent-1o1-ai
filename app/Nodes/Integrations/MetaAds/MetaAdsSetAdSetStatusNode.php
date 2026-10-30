<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsSetAdSetStatusNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_set_ad_set_status';
    }

    public function name(): string
    {
        return 'Meta Ads: Set Ad Set Status';
    }

    public function description(): string
    {
        return 'Activates or pauses a ad set. An active ad set spends money, as long as what it belongs to is active too.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->statusEffect($config);
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ad_set_id', 'status'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'ad_set_id' => Field::dynamic('Ad set', 'meta_ads.ad_sets', null, null, ['account_id']),
                'status' => Field::select('Status', ['PAUSED' => 'Paused', 'ACTIVE' => 'Active (spends money)'], null, 'PAUSED'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'ad_set_id', 'The ad set');
        $status = $this->statusOf($config);

        $this->graph($run, $config, 'POST', "/{$id}", ['status' => $status]);

        return ['id' => $id, 'status' => $status];
    }
}
