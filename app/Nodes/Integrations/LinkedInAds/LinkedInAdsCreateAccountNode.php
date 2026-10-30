<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsCreateAccountNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_create_account';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Create Ad Account';
    }

    public function description(): string
    {
        return 'Creates an ad account for a company page. It spends nothing until it has billing and campaigns. A member can hold a limited number of them.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'reference', 'currency'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
                'reference' => Field::text('Company page', 'The URN it advertises for, like urn:li:organization:123.'),
                'currency' => Field::text('Currency', 'Three letters, like USD.'),
                'test' => Field::advanced(Field::boolean('Test account', 'Serves no ads to members. Can only be set now.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = ['name' => $this->requiredString($config, 'name', 'The name'), 'reference' => $this->anyUrn($config, 'reference', 'The company page'), 'currency' => strtoupper($this->requiredString($config, 'currency', 'The currency')), 'type' => 'BUSINESS', 'test' => (bool) ($config['test'] ?? false)];

        return ['created' => true, 'id' => $this->create($run, $config, '/adAccounts', $body)];
    }
}
