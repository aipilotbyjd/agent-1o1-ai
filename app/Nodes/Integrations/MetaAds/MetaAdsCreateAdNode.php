<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsCreateAdNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_create_ad';
    }

    public function name(): string
    {
        return 'Meta Ads: Create Ad';
    }

    public function description(): string
    {
        return 'Creates an ad by combining an ad set with a creative. Paused by default, and every ad goes through Meta\'s review.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->statusEffect($config);
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'ad_set_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'ad_set_id' => Field::dynamic('Ad set', 'meta_ads.ad_sets', null, null, ['account_id']),
                'name' => Field::text('Name'),
                'creative_id' => Field::dynamic('Creative', 'meta_ads.creatives', null, null, ['account_id']),
                'creative' => Field::advanced(Field::json('Or a new creative', 'A creative spec, instead of choosing one.')),
                'status' => Field::select('Status', ['PAUSED' => 'Paused (spends nothing)', 'ACTIVE' => 'Active (spends money)'], null, 'PAUSED'),
                'conversion_domain' => Field::advanced(Field::text('Conversion domain', 'Like example.com, for campaigns that use a pixel.')),
                'tracking_specs' => Field::advanced(Field::json('Tracking', 'The Marketing API\'s tracking_specs.', 'array')),
                'more_fields' => Field::advanced(Field::json('More fields', 'Any other field of the Marketing API\'s ad.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $params = [...$this->jsonSetting($config, 'more_fields', 'More fields'), ...$this->adParams($config)];
        $created = $this->graph($run, $config, 'POST', '/'.$this->accountPath($config).'/ads', $params);

        return ['id' => $created['id'] ?? null, 'status' => $params['status']];
    }
}
