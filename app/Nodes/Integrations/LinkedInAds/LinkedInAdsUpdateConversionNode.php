<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsUpdateConversionNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_update_conversion';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Update Conversion Rule';
    }

    public function description(): string
    {
        return 'Renames a conversion rule, or switches it on or off.';
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
                'name' => Field::text('Name'),
                'enabled' => Field::select('Enabled', ['true' => 'On', 'false' => 'Off']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'conversion_id', 'The conversion rule');
        $set = $this->only($config, ['name']);

        if (filled($config['enabled'] ?? null)) {
            $set['enabled'] = filter_var($config['enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        $this->patch($run, $config, "/conversions/{$id}", $set, 'account='.rawurlencode($this->urn($config, 'account_id', 'The ad account', 'sponsoredAccount')));

        return ['updated' => true, 'id' => $id];
    }
}
