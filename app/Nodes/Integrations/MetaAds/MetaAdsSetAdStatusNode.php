<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsSetAdStatusNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_set_ad_status';
    }

    public function name(): string
    {
        return 'Meta Ads: Set Ad Status';
    }

    public function description(): string
    {
        return 'Activates or pauses a ad. An active ad spends money, as long as what it belongs to is active too.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->statusEffect($config);
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ad_id', 'status'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'ad_id' => Field::dynamic('Ad', 'meta_ads.ads', null, null, ['account_id']),
                'status' => Field::select('Status', ['PAUSED' => 'Paused', 'ACTIVE' => 'Active (spends money)'], null, 'PAUSED'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'ad_id', 'The ad');
        $status = $this->statusOf($config);

        $this->graph($run, $config, 'POST', "/{$id}", ['status' => $status]);

        return ['id' => $id, 'status' => $status];
    }
}
