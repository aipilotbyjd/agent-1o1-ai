<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogCreateApiSyntheticNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_api_synthetic';
    }

    public function name(): string
    {
        return 'Datadog: Create API Test';
    }

    public function description(): string
    {
        return 'Creates a Synthetic API test.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'locations', 'config', 'options'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
                'locations' => Field::list('Locations', 'Like aws:eu-central-1.'),
                'config' => Field::json('Test definition', 'The request and assertions, as in the Datadog API.'),
                'options' => Field::json('Options', 'Like {"tick_every":300}.'),
                'message' => Field::textarea('Notification message'),
                'tags' => Field::list('Tags', 'Like env:prod.'),
                'subtype' => Field::advanced(Field::select('API test kind', ['http' => 'HTTP', 'ssl' => 'SSL', 'tcp' => 'TCP', 'dns' => 'DNS', 'multi' => 'Multistep', 'icmp' => 'ICMP', 'udp' => 'UDP', 'websocket' => 'WebSocket', 'grpc' => 'gRPC'])),
                'status' => Field::advanced(Field::select('Status', ['live' => 'Live', 'paused' => 'Paused'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $changes = array_filter([
            'name' => $this->text($config, 'name'),
            'locations' => $this->listOf($config, 'locations') ?: null,
            'config' => $this->jsonOf($config, 'config', 'Test definition'),
            'options' => $this->jsonOf($config, 'options', 'Options'),
            'message' => $this->text($config, 'message'),
            'tags' => $this->listOf($config, 'tags') ?: null,
            'subtype' => $this->text($config, 'subtype'),
            'status' => $this->text($config, 'status'),
        ], fn (mixed $value): bool => $value !== null);

        foreach (['name', 'locations', 'config', 'options'] as $required) {
            if (! isset($changes[$required])) {
                throw new InvalidArgumentException("The {$required} setting is required.");
            }
        }

        return $this->datadog($run, $config, 'POST', '/api/v1/synthetics/tests/api', [], ['type' => 'api', 'subtype' => 'http', 'message' => '', ...$changes]);
    }
}
