<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsCreateLeadFormNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_create_lead_form';
    }

    public function name(): string
    {
        return 'Meta Ads: Create Lead Form';
    }

    public function description(): string
    {
        return 'Creates a lead form on a Page, for lead ads to use.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['page_id', 'name', 'questions'],
            'properties' => [
                ...$this->credentialFields(),
                'page_id' => Field::dynamic('Page', 'meta_ads.pages'),
                'name' => Field::text('Name'),
                'questions' => Field::json('Questions', 'A list, each with type like FULL_NAME, EMAIL or PHONE. A CUSTOM one also has key, label and options.', 'array'),
                'privacy_policy' => Field::json('Privacy policy', 'An object with url and link_text.'),
                'follow_up_action_url' => Field::text('Website after sending'),
                'locale' => Field::advanced(Field::text('Locale', 'Like en_US.')),
                'more_fields' => Field::advanced(Field::json('More fields', 'Any other field, like thank_you_page.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $page = $this->nodeId($config, 'page_id', 'The Page');

        return $this->graph($run, $config, 'POST', "/{$page}/leadgen_forms", [...$this->jsonSetting($config, 'more_fields', 'More fields'), 'name' => $this->requiredString($config, 'name', 'The name'), 'questions' => $this->jsonSetting($config, 'questions', 'The questions', true), 'privacy_policy' => $this->jsonSetting($config, 'privacy_policy', 'The privacy policy') ?: null, ...$this->only($config, ['follow_up_action_url', 'locale'])], $this->pageToken($run, $config, $page));
    }
}
