<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsCopyCampaignNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_copy_campaign';
    }

    public function name(): string
    {
        return 'Meta Ads: Copy Campaign';
    }

    public function description(): string
    {
        return 'Duplicates a campaign, paused by default.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->statusEffect($config, 'status_option');
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'campaign_id' => Field::dynamic('Campaign', 'meta_ads.campaigns', null, null, ['account_id']),
                'rename_prefix' => Field::advanced(Field::text('Name prefix')),
                'rename_suffix' => Field::advanced(Field::text('Name suffix', 'Empty is Meta\'s own, like - Copy.')),
                'status_option' => Field::select('Copy starts', ['PAUSED' => 'Paused (spends nothing)', 'INHERITED_FROM_SOURCE' => 'As the original (can spend money)', 'ACTIVE' => 'Active (spends money)'], null, 'PAUSED'),
                'deep_copy' => Field::advanced(Field::boolean('Copy its ad sets and ads too')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->graph($run, $config, 'POST', '/'.$this->nodeId($config, 'campaign_id', 'The campaign').'/copies', ['deep_copy' => (bool) ($config['deep_copy'] ?? false), 'status_option' => $config['status_option'] ?? 'PAUSED', 'rename_options' => array_filter(['rename_strategy' => 'ONLY_TOP_LEVEL_RENAME', 'rename_prefix' => $config['rename_prefix'] ?? null, 'rename_suffix' => $config['rename_suffix'] ?? null])]);
    }
}
