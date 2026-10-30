<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MetaAdsUpdateAdSetNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_update_ad_set';
    }

    public function name(): string
    {
        return 'Meta Ads: Update Ad Set';
    }

    public function description(): string
    {
        return 'Changes the fields you fill in on an ad set. A higher budget, a wider audience or making it active spends more money.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->changeEffect($config);
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ad_set_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'ad_set_id' => Field::dynamic('Ad set', 'meta_ads.ad_sets', null, null, ['account_id']),
                'name' => Field::text('Name'),
                'status' => Field::select('Status', ['PAUSED' => 'Paused', 'ACTIVE' => 'Active (spends money)', 'ARCHIVED' => 'Archived']),
                'daily_budget' => Field::advanced(Field::integer('Daily budget', 'In the smallest unit of the currency.')),
                'lifetime_budget' => Field::advanced(Field::integer('Lifetime budget', 'In the smallest unit of the currency.')),
                'bid_amount' => Field::advanced(Field::integer('Bid', 'In the smallest unit of the currency.')),
                'end_time' => Field::advanced(Field::datetime('End')),
                'targeting' => Field::advanced(Field::json('Targeting', 'The Marketing API\'s targeting spec. It replaces the one there.')),
                'more_fields' => Field::advanced(Field::json('More fields', 'Any other field to change.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'ad_set_id', 'The ad set');
        $params = [...$this->jsonSetting($config, 'more_fields', 'More fields'), ...$this->only($config, ['name', 'status', 'daily_budget', 'lifetime_budget', 'bid_amount', 'end_time', 'targeting'])];

        if ($params === []) {
            throw new InvalidArgumentException('Fill in at least one field to change.');
        }

        return ['id' => $id, 'updated' => (bool) ($this->graph($run, $config, 'POST', "/{$id}", $params)['success'] ?? true)];
    }
}
