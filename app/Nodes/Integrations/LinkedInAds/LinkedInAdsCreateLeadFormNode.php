<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsCreateLeadFormNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_create_lead_form';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Create Lead Form';
    }

    public function description(): string
    {
        return 'Creates a lead form. It collects nothing until it is published and used by a campaign.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'content'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->formOwnerFields(),
                'name' => Field::text('Name'),
                'locale_country' => Field::text('Locale country', 'Two letters, like US.', 'US'),
                'locale_language' => Field::text('Locale language', 'Two letters, like en.', 'en'),
                'state' => Field::select('State', ['DRAFT' => 'Draft', 'PUBLISHED' => 'Published'], null, 'DRAFT'),
                'content' => Field::json('Content', 'LinkedIn\'s form content: headline, description, questions, legalInfo and postSubmissionInfo.'),
                'hidden_fields' => Field::advanced(Field::json('Hidden fields', 'A list of name and value pairs sent with each lead.', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = array_filter(['owner' => $this->formOwnerBody($config), 'name' => $this->requiredString($config, 'name', 'The name'), 'creationLocale' => ['country' => strtoupper((string) ($config['locale_country'] ?? 'US')), 'language' => strtolower((string) ($config['locale_language'] ?? 'en'))], 'state' => strtoupper((string) ($config['state'] ?? 'DRAFT')), 'content' => $this->jsonSetting($config, 'content', 'The content', true), 'hiddenFields' => $this->jsonSetting($config, 'hidden_fields', 'The hidden fields') ?: null]);

        return ['created' => true, 'id' => $this->create($run, $config, '/leadForms', $body)];
    }
}
