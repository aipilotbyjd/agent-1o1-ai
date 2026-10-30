<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsCopyAdNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_copy_ad';
    }

    public function name(): string
    {
        return 'Meta Ads: Copy Ad';
    }

    public function description(): string
    {
        return 'Duplicates an ad, paused by default.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->statusEffect($config, 'status_option');
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ad_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'ad_id' => Field::dynamic('Ad', 'meta_ads.ads', null, null, ['account_id']),
                'ad_set_id' => Field::advanced(Field::dynamic('To ad set', 'meta_ads.ad_sets', 'Empty keeps the original.', null, ['account_id'])),
                'rename_prefix' => Field::advanced(Field::text('Name prefix')),
                'rename_suffix' => Field::advanced(Field::text('Name suffix', 'Empty is Meta\'s own, like - Copy.')),
                'status_option' => Field::select('Copy starts', ['PAUSED' => 'Paused (spends nothing)', 'INHERITED_FROM_SOURCE' => 'As the original (can spend money)', 'ACTIVE' => 'Active (spends money)'], null, 'PAUSED'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->graph($run, $config, 'POST', '/'.$this->nodeId($config, 'ad_id', 'The ad').'/copies', ['adset_id' => filled($config['ad_set_id'] ?? null) ? $this->nodeId($config, 'ad_set_id', 'The ad set') : null, 'status_option' => $config['status_option'] ?? 'PAUSED', 'rename_options' => array_filter(['rename_strategy' => 'ONLY_TOP_LEVEL_RENAME', 'rename_prefix' => $config['rename_prefix'] ?? null, 'rename_suffix' => $config['rename_suffix'] ?? null])]);
    }
}
