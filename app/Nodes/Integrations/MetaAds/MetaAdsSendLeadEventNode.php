<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsSendLeadEventNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_send_lead_event';
    }

    public function name(): string
    {
        return 'Meta Ads: Send Lead Event';
    }

    public function description(): string
    {
        return 'Sends a lead from your server to a pixel or dataset through the Conversions API.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['pixel_id', 'action_source'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'action_source' => Field::select('Happened on', ['website' => 'Website', 'app' => 'App', 'email' => 'Email', 'phone_call' => 'Phone call', 'chat' => 'Chat', 'physical_store' => 'Physical store', 'system_generated' => 'System generated', 'business_messaging' => 'Business messaging', 'other' => 'Other'], null, 'website'),
                ...$this->conversionFields(),
                'value' => Field::text('Value', 'An amount, like 100.20.'),
                'currency' => Field::text('Currency', 'Three letters, like USD.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->sendConversion($run, $config, ['event_name' => 'Lead', 'action_source' => $this->requiredString($config, 'action_source', 'Where it happened')], $this->only($config, ['value', 'currency']));
    }
}
