<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsCreateAudienceNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_create_audience';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Create Audience';
    }

    public function description(): string
    {
        return 'Creates an audience that contacts or companies can be added to.';
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
                'type' => Field::select('Holds', ['USER' => 'Contacts', 'COMPANY' => 'Companies'], null, 'USER'),
                'description' => Field::text('Description'),
                'source_platform' => Field::advanced(Field::select('Source', ['DIRECT_API' => 'Own ad account', 'PARTNER_API' => 'Partner'], null, 'DIRECT_API')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = array_filter(['account' => $this->urn($config, 'account_id', 'The ad account', 'sponsoredAccount'), 'name' => $this->requiredString($config, 'name', 'The name'), 'type' => $this->requiredString($config, 'type', 'What it holds'), 'description' => $config['description'] ?? null, 'sourcePlatform' => $config['source_platform'] ?? 'DIRECT_API', 'destinations' => [['destination' => 'LINKEDIN']]]);

        return ['created' => true, 'id' => $this->create($run, $config, '/dmpSegments', $body)];
    }
}
