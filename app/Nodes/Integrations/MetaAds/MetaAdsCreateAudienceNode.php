<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsCreateAudienceNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_create_audience';
    }

    public function name(): string
    {
        return 'Meta Ads: Create Custom Audience';
    }

    public function description(): string
    {
        return 'Creates a custom audience that people can then be added to. The account must have accepted Meta\'s Custom Audience terms.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'name' => Field::text('Name'),
                'description' => Field::text('Description'),
                'customer_file_source' => Field::select('Where the data comes from', ['USER_PROVIDED_ONLY' => 'Directly from the people', 'PARTNER_PROVIDED_ONLY' => 'From partners', 'BOTH_USER_AND_PARTNER_PROVIDED' => 'Both'], null, 'USER_PROVIDED_ONLY'),
                'more_fields' => Field::advanced(Field::json('More fields', 'Any other field, like rule for a website audience.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->graph($run, $config, 'POST', '/'.$this->accountPath($config).'/customaudiences', [...$this->jsonSetting($config, 'more_fields', 'More fields'), 'name' => $this->requiredString($config, 'name', 'The name'), 'subtype' => 'CUSTOM', ...$this->only($config, ['description', 'customer_file_source'])]);
    }
}
