<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogValidateMonitorNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_validate_monitor';
    }

    public function name(): string
    {
        return 'Datadog: Validate Monitor';
    }

    public function description(): string
    {
        return 'Checks a monitor definition without saving it, and says what is wrong with it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['type', 'query'],
            'properties' => [
                ...$this->credentialFields(),
                'type' => Field::text('Type', null, 'metric alert'),
                'query' => Field::textarea('Query'),
                'name' => Field::text('Name'),
                'message' => Field::textarea('Message'),
                'options' => Field::advanced(Field::json('Options')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [
            'type' => $this->requiredString($config, 'type', 'The type'),
            'query' => $this->requiredString($config, 'query', 'The query'),
            'name' => $this->text($config, 'name'),
            'message' => $this->text($config, 'message'),
            'options' => $this->jsonOf($config, 'options', 'Options'),
        ];
        $response = $this->datadogResponse($run, $config, 'POST', '/api/v1/monitor/validate', [], array_filter($body, fn (mixed $value): bool => $value !== null));

        if ($response->status() === 400) {
            return ['valid' => false, 'errors' => array_values((array) ($response->json('errors') ?? []))];
        }

        $this->assertOk($response);

        return ['valid' => true, 'errors' => []];
    }
}
