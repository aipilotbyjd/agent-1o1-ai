<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsListActivitiesNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_activities';
    }

    public function name(): string
    {
        return 'Meta Ads: List Account Activity';
    }

    public function description(): string
    {
        return 'Lists what has been changed in an ad account and by whom.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'since' => Field::text('Since', 'YYYY-MM-DD.'),
                'until' => Field::text('Until', 'YYYY-MM-DD.'),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->accountPath($config).'/activities', [...$this->only($config, ['since', 'until']), ...$this->paging($config)]), 'activities');
    }
}
