<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsSearchTargetingNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_search_targeting';
    }

    public function name(): string
    {
        return 'Meta Ads: Search Targeting Options';
    }

    public function description(): string
    {
        return 'Looks up the interests, locations, employers and other options that targeting can use, with their ids.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['type'],
            'properties' => [
                ...$this->credentialFields(),
                'type' => Field::select('Search', ['adinterest' => 'Interests', 'adgeolocation' => 'Locations', 'adlocale' => 'Languages', 'adTargetingCategory' => 'Browse categories', 'adeducationschool' => 'Schools', 'adeducationmajor' => 'Fields of study', 'adworkemployer' => 'Employers', 'adworkposition' => 'Job titles'], null, 'adinterest'),
                'q' => Field::text('Search for'),
                'class' => Field::advanced(Field::select('Category', ['interests', 'behaviors', 'demographics', 'life_events', 'industries', 'income', 'family_statuses', 'user_device', 'user_os'], 'For Browse categories.')),
                'location_types' => Field::advanced(Field::list('Location types', 'For Locations: country, region, city, zip, geo_market and so on.')),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->graph($run, $config, 'GET', '/search', [...$this->only($config, ['q', 'class', 'limit']), 'type' => $this->requiredString($config, 'type', 'The search'), 'location_types' => $this->stringList($config, 'location_types') ?: null]);

        return $this->listResult($body, 'options');
    }
}
