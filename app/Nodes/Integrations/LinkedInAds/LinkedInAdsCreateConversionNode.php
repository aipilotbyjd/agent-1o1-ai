<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsCreateConversionNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_create_conversion';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Create Conversion Rule';
    }

    public function description(): string
    {
        return 'Creates a conversion rule, which server events can then be sent to.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'name', 'type'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'name' => Field::text('Name'),
                'type' => Field::text('Type', 'Like LEAD, PURCHASE, ADD_TO_CART, SIGN_UP, INSTALL, KEY_PAGE_VIEW, DOWNLOAD, START_CHECKOUT, SCHEDULE, VIEW_CONTENT, VIEW_VIDEO or OTHER.', 'LEAD'),
                'attribution_type' => Field::advanced(Field::select('Attribution', ['LAST_TOUCH_BY_CAMPAIGN' => 'Last touch, by campaign', 'LAST_TOUCH_BY_CONVERSION' => 'Last touch, by conversion'], null, 'LAST_TOUCH_BY_CAMPAIGN')),
                'post_click_days' => Field::advanced(Field::integer('Days after a click', null, 30, 1, 365)),
                'view_through_days' => Field::advanced(Field::integer('Days after a view', null, 7, 1, 365)),
                'auto_association' => Field::advanced(Field::select('Add to campaigns', ['NONE' => 'No', 'ALL_CAMPAIGNS' => 'All', 'OBJECTIVE_BASED' => 'By objective'], null, 'NONE')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = ['name' => $this->requiredString($config, 'name', 'The name'), 'account' => $this->urn($config, 'account_id', 'The ad account', 'sponsoredAccount'), 'conversionMethod' => 'CONVERSIONS_API', 'postClickAttributionWindowSize' => (int) ($config['post_click_days'] ?? 30), 'viewThroughAttributionWindowSize' => (int) ($config['view_through_days'] ?? 7), 'attributionType' => $config['attribution_type'] ?? 'LAST_TOUCH_BY_CAMPAIGN', 'type' => $this->requiredString($config, 'type', 'The type')];
        $association = (string) ($config['auto_association'] ?? 'NONE');

        return ['created' => true, 'id' => $this->create($run, $config, '/conversions', $body, in_array($association, ['ALL_CAMPAIGNS', 'OBJECTIVE_BASED'], true) ? "autoAssociationType={$association}" : '')];
    }
}
