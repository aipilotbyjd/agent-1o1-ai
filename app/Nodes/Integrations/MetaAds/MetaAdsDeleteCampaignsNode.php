<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsDeleteCampaignsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_delete_campaigns';
    }

    public function name(): string
    {
        return 'Meta Ads: Delete Campaigns';
    }

    public function description(): string
    {
        return 'Deletes many campaigns of an ad account at once: any, the oldest, or those archived before a date. This cannot be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'delete_strategy'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'delete_strategy' => Field::select('Delete', ['DELETE_ARCHIVED_BEFORE' => 'Those archived before the date', 'DELETE_OLDEST' => 'The oldest', 'DELETE_ANY' => 'Any'], null, 'DELETE_ARCHIVED_BEFORE'),
                'before_date' => Field::datetime('Before'),
                'object_count' => Field::integer('How many', null, null, 1),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->graph($run, $config, 'DELETE', '/'.$this->accountPath($config).'/campaigns', ['delete_strategy' => $this->requiredString($config, 'delete_strategy', 'What to delete'), ...$this->only($config, ['before_date', 'object_count'])]);
    }
}
