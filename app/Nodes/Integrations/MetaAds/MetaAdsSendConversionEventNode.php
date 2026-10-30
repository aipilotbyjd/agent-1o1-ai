<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsSendConversionEventNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_send_conversion_event';
    }

    public function name(): string
    {
        return 'Meta Ads: Send Conversion Event';
    }

    public function description(): string
    {
        return 'Sends a named event from your server to a pixel or dataset through the Conversions API.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['pixel_id', 'event_name', 'action_source'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'event_name' => Field::text('Event name', 'A standard one, like AddToCart, or your own.'),
                'action_source' => Field::select('Happened on', ['website' => 'Website', 'app' => 'App', 'email' => 'Email', 'phone_call' => 'Phone call', 'chat' => 'Chat', 'physical_store' => 'Physical store', 'system_generated' => 'System generated', 'business_messaging' => 'Business messaging', 'other' => 'Other'], null, 'website'),
                ...$this->conversionFields(),
                'value' => Field::text('Value', 'An amount, like 100.20.'),
                'currency' => Field::text('Currency', 'Three letters, like USD.'),
                'content_ids' => Field::advanced(Field::list('Product ids')),
                'content_type' => Field::advanced(Field::text('Content type', 'Like product.')),
                'custom_data' => Field::advanced(Field::json('More custom data', 'Any other values of the event.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $custom = [...$this->jsonSetting($config, 'custom_data', 'The custom data'), ...$this->only($config, ['value', 'currency', 'content_type']), 'content_ids' => $this->stringList($config, 'content_ids') ?: null];

        return $this->sendConversion($run, $config, ['event_name' => $this->requiredString($config, 'event_name', 'The event name'), 'action_source' => $this->requiredString($config, 'action_source', 'Where it happened')], $custom);
    }
}
