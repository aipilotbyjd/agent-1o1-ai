<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogUpdateWebhookNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_webhook';
    }

    public function name(): string
    {
        return 'Datadog: Update Webhook';
    }

    public function description(): string
    {
        return 'Changes a webhook configuration.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
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

        $name = $this->pathId($config, 'name', 'The name');

        if ($body === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return $this->datadog($run, $config, 'PUT', "/api/v1/integration/webhooks/configuration/webhooks/{$name}", [], $body);
    }
}
