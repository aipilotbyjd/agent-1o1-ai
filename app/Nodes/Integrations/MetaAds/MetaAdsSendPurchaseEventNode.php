<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsSendPurchaseEventNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_send_purchase_event';
    }

    public function name(): string
    {
        return 'Meta Ads: Send Purchase Event';
    }

    public function description(): string
    {
        return 'Sends a purchase from your server to a pixel or dataset through the Conversions API.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['pixel_id', 'action_source', 'value', 'currency'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'action_source' => Field::select('Happened on', ['website' => 'Website', 'app' => 'App', 'email' => 'Email', 'phone_call' => 'Phone call', 'chat' => 'Chat', 'physical_store' => 'Physical store', 'system_generated' => 'System generated', 'business_messaging' => 'Business messaging', 'other' => 'Other'], null, 'website'),
                ...$this->conversionFields(),
                'value' => Field::text('Value', 'An amount, like 100.20.'),
                'currency' => Field::text('Currency', 'Three letters, like USD.'),
                'content_ids' => Field::advanced(Field::list('Product ids')),
                'order_id' => Field::advanced(Field::text('Order id')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $custom = ['value' => $this->requiredString($config, 'value', 'The value'), 'currency' => strtoupper($this->requiredString($config, 'currency', 'The currency')), 'content_ids' => $this->stringList($config, 'content_ids') ?: null, 'order_id' => $config['order_id'] ?? null];

        return $this->sendConversion($run, $config, ['event_name' => 'Purchase', 'action_source' => $this->requiredString($config, 'action_source', 'Where it happened')], $custom);
    }
}
