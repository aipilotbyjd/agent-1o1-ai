<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogSubmitServiceCheckNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_submit_service_check';
    }

    public function name(): string
    {
        return 'Datadog: Submit Service Check';
    }

    public function description(): string
    {
        return 'Reports the status of a service check to Datadog.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['check', 'host', 'status'],
            'properties' => [
                ...$this->credentialFields(),
                'check' => Field::text('Check', null, 'app.is_ok'),
                'host' => Field::text('Host', null, 'web-1'),
                'status' => Field::select('Status', ['0' => 'OK', '1' => 'Warning', '2' => 'Critical', '3' => 'Unknown'], null, '0'),
                'message' => Field::textarea('Message'),
                'tags' => Field::list('Tags', 'Like env:prod.'),
                'timestamp' => Field::advanced(Field::datetime('Time', 'Default now.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = (string) ($config['status'] ?? '0');

        if (! in_array($status, ['0', '1', '2', '3'], true)) {
            throw new InvalidArgumentException('The status must be OK, warning, critical or unknown.');
        }

        $check = array_filter([
            'check' => $this->requiredString($config, 'check', 'The check'),
            'host_name' => $this->requiredString($config, 'host', 'The host'),
            'status' => (int) $status,
            'message' => $this->text($config, 'message'),
            'tags' => $this->listOf($config, 'tags'),
            'timestamp' => filled($config['timestamp'] ?? null) ? $this->unixTime($config['timestamp'], 'The time') : null,
        ], fn (mixed $value): bool => $value !== null);
        $result = $this->datadog($run, $config, 'POST', '/api/v1/check_run', [], [$check]);

        return ['accepted' => true, 'status' => $result['status'] ?? null];
    }
}
