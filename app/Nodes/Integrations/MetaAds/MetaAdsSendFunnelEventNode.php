<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsSendFunnelEventNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_send_funnel_event';
    }

    public function name(): string
    {
        return 'Meta Ads: Send Lead Funnel Event';
    }

    public function description(): string
    {
        return 'Tells Meta how a lead has moved on in your CRM, by its lead id, so that ads can optimize for quality leads.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['pixel_id', 'event_name', 'lead_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'pixel_id' => Field::dynamic('Pixel', 'meta_ads.pixels', null, null, ['account_id']),
                'event_name' => Field::text('Lifecycle stage', 'The stage in your CRM, like Qualified or Converted.'),
                'lead_id' => Field::text('Lead id', 'The id of the Meta lead.'),
                'lead_event_source' => Field::text('CRM name', 'Like Salesforce or HubSpot.'),
                'event_time' => Field::datetime('Happened at', 'Within the last 7 days. Empty is now.'),
                'test_event_code' => Field::advanced(Field::text('Test event code', 'From Events Manager, while testing. Events with it are still counted.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->sendConversion($run, $config, ['event_name' => $this->requiredString($config, 'event_name', 'The stage'), 'action_source' => 'system_generated'], ['event_source' => 'crm', 'lead_event_source' => $config['lead_event_source'] ?? null], ['lead_id' => $this->nodeId($config, 'lead_id', 'The lead id')]);
    }
}
