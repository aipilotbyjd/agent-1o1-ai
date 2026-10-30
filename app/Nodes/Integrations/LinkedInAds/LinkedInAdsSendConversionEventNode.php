<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class LinkedInAdsSendConversionEventNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_send_conversion_event';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Send Conversion Event';
    }

    public function description(): string
    {
        return 'Sends a conversion from your server to LinkedIn, on a rule measured by server events, for a person matched by email or click id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'conversion_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'conversion_id' => Field::dynamic('Conversion rule', 'linkedin_ads.conversions', null, null, ['account_id']),
                'email' => Field::text('Email', 'Sent as a SHA-256 hash, as LinkedIn requires.'),
                'click_id' => Field::text('LinkedIn click id', 'The li_fat_id of the visit.'),
                'happened_at' => Field::datetime('Happened at', 'Within the last 90 days. Empty is now.'),
                'value' => Field::text('Value', 'An amount, like 50.00.'),
                'currency' => Field::text('Currency', 'Three letters, like USD.'),
                'event_id' => Field::advanced(Field::text('Event id', 'The same id as the Insight Tag event, so it is counted once.')),
                'first_name' => Field::advanced(Field::text('First name')),
                'last_name' => Field::advanced(Field::text('Last name')),
                'title' => Field::advanced(Field::text('Job title')),
                'company' => Field::advanced(Field::text('Company')),
                'country' => Field::advanced(Field::text('Country', 'Two letters, like US.')),
                'lead' => Field::advanced(Field::text('Lead response', 'A URN like urn:li:leadGenFormResponse:abc, for a lead that later converted.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $userIds = [];

        if (filled($config['email'] ?? null)) {
            $userIds[] = ['idType' => 'SHA256_EMAIL', 'idValue' => hash('sha256', strtolower(trim((string) $config['email'])))];
        }

        if (filled($config['click_id'] ?? null)) {
            $userIds[] = ['idType' => 'LINKEDIN_FIRST_PARTY_ADS_TRACKING_UUID', 'idValue' => (string) $config['click_id']];
        }

        if ($userIds === [] && blank($config['lead'] ?? null)) {
            throw new InvalidArgumentException('Give an email, a click id or a lead response, so LinkedIn can match the person.');
        }

        $userInfo = array_filter(['firstName' => $config['first_name'] ?? null, 'lastName' => $config['last_name'] ?? null, 'title' => $config['title'] ?? null, 'companyName' => $config['company'] ?? null, 'countryCode' => $config['country'] ?? null]);
        $body = array_filter([
            'conversion' => 'urn:lla:llaPartnerConversion:'.$this->numericId($config, 'conversion_id', 'The conversion rule'),
            'conversionHappenedAt' => $this->epochMs($config['happened_at'] ?? now()->toIso8601String()),
            'conversionValue' => filled($config['value'] ?? null) ? ['currencyCode' => strtoupper($this->requiredString($config, 'currency', 'The currency')), 'amount' => (string) $config['value']] : null,
            'user' => array_filter(['userIds' => $userIds, 'userInfo' => $userInfo, 'lead' => $config['lead'] ?? null]),
            'eventId' => $config['event_id'] ?? null,
        ]);

        $this->linkedin($run, $config, 'POST', '/conversionEvents', '', $body);

        return ['sent' => true, 'conversion' => $body['conversion']];
    }
}
