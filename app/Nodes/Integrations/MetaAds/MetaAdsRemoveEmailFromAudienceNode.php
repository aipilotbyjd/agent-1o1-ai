<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MetaAdsRemoveEmailFromAudienceNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_remove_email_from_audience';
    }

    public function name(): string
    {
        return 'Meta Ads: Remove Emails from Audience';
    }

    public function description(): string
    {
        return 'Removes people from a custom audience by email.';
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
        $body = $this->graph($run, $config, 'POST', '/'.$this->nodeId($config, 'audience_id', 'The audience').'/users', ['payload' => $payload, ...$this->audienceSession('DELETE', count($emails))]);

        return ['removed' => count($emails), 'num_received' => $body['num_received'] ?? null, 'num_invalid_entries' => $body['num_invalid_entries'] ?? null, 'session_id' => $body['session_id'] ?? null];
    }
}
