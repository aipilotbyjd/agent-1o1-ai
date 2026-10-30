<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MetaAdsUpdateAdNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_update_ad';
    }

    public function name(): string
    {
        return 'Meta Ads: Update Ad';
    }

    public function description(): string
    {
        return 'Changes the fields you fill in on an ad. Making it active spends money.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->changeEffect($config);
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
                'name' => Field::text('Name'),
                'status' => Field::select('Status', ['PAUSED' => 'Paused', 'ACTIVE' => 'Active (spends money)', 'ARCHIVED' => 'Archived']),
                'creative_id' => Field::dynamic('Creative', 'meta_ads.creatives', null, null, ['account_id']),
                'tracking_specs' => Field::advanced(Field::json('Tracking', 'The Marketing API\'s tracking_specs.', 'array')),
                'more_fields' => Field::advanced(Field::json('More fields', 'Any other field to change.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'ad_id', 'The ad');
        $params = [...$this->jsonSetting($config, 'more_fields', 'More fields'), ...$this->only($config, ['name', 'status', 'tracking_specs'])];

        if (filled($config['creative_id'] ?? null)) {
            $params['creative'] = ['creative_id' => $this->nodeId($config, 'creative_id', 'The creative')];
        }

        if ($params === []) {
            throw new InvalidArgumentException('Fill in at least one field to change.');
        }

        return ['id' => $id, 'updated' => (bool) ($this->graph($run, $config, 'POST', "/{$id}", $params)['success'] ?? true)];
    }
}
