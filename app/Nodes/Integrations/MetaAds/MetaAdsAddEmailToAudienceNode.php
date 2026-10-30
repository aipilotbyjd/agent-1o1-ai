<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MetaAdsAddEmailToAudienceNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_add_email_to_audience';
    }

    public function name(): string
    {
        return 'Meta Ads: Add Emails to Audience';
    }

    public function description(): string
    {
        return 'Adds people to a custom audience by email. Up to 10,000 at a time, sent as SHA-256 hashes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['audience_id', 'emails'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'audience_id' => Field::dynamic('Audience', 'meta_ads.audiences', null, null, ['account_id']),
                'emails' => Field::list('Emails'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $emails = $this->stringList($config, 'emails');

        if ($emails === [] || count($emails) > 10000) {
            throw new InvalidArgumentException('Give between 1 and 10,000 emails.');
        }

        $payload = ['schema' => 'EMAIL_SHA256', 'data' => array_map(fn (string $email): array => [hash('sha256', mb_strtolower($email))], $emails)];
        $body = $this->graph($run, $config, 'POST', '/'.$this->nodeId($config, 'audience_id', 'The audience').'/users', ['payload' => $payload, ...$this->audienceSession('POST', count($emails))]);

        return ['added' => count($emails), 'num_received' => $body['num_received'] ?? null, 'num_invalid_entries' => $body['num_invalid_entries'] ?? null, 'session_id' => $body['session_id'] ?? null];
    }
}
