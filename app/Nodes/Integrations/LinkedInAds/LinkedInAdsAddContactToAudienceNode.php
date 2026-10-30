<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsAddContactToAudienceNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_add_contact_to_audience';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Add Contact to Audience';
    }

    public function description(): string
    {
        return 'Adds a person to an audience by email or name.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['segment_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'segment_id' => Field::dynamic('Audience', 'linkedin_ads.segments', null, null, ['account_id']),
                'email' => Field::text('Email', 'Sent as a SHA-256 hash, as LinkedIn requires.'),
                'first_name' => Field::advanced(Field::text('First name')),
                'last_name' => Field::advanced(Field::text('Last name')),
                'title' => Field::advanced(Field::text('Job title')),
                'company' => Field::advanced(Field::text('Company')),
                'country' => Field::advanced(Field::text('Country', 'Two letters, like US.')),
                'google_aid' => Field::advanced(Field::text('Mobile ad id')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'segment_id', 'The audience');

        $this->linkedin($run, $config, 'POST', "/dmpSegments/{$id}/users", '', $this->audienceUser($config, 'ADD'));

        return ['added' => true, 'audience_id' => $id];
    }
}
