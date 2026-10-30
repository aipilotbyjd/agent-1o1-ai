<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogCreateWebhookNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_webhook';
    }

    public function name(): string
    {
        return 'Datadog: Create Webhook';
    }

    public function description(): string
    {
        return 'Creates a webhook configuration, for monitors to mention as @webhook-name.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'url'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', 'Mentioned in monitors as @webhook-name.', 'my-webhook'),
                'url' => Field::text('URL', null, 'https://example.com/hook'),
                'payload' => Field::advanced(Field::textarea('Payload', 'JSON with Datadog variables like $EVENT_TITLE.')),
                'custom_headers' => Field::advanced(Field::textarea('Custom headers', 'A JSON object, as text.')),
                'encode_as' => Field::advanced(Field::select('Encoding', ['json' => 'JSON', 'form' => 'Form'], null, 'json')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = array_filter([
            'url' => $this->text($config, 'url'),
            'payload' => $this->text($config, 'payload'),
            'custom_headers' => $this->text($config, 'custom_headers'),
            'encode_as' => $this->text($config, 'encode_as'),
        ], fn (mixed $value): bool => $value !== null);

        $this->requiredString($config, 'url', 'The URL');

        return $this->datadog($run, $config, 'POST', '/api/v1/integration/webhooks/configuration/webhooks', [], ['name' => $this->requiredString($config, 'name', 'The name'), ...$body]);
    }
}
