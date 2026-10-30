<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogMuteHostNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_mute_host';
    }

    public function name(): string
    {
        return 'Datadog: Mute Host';
    }

    public function description(): string
    {
        return 'Mutes a host so it stops alerting, for good or until a set time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['host'],
            'properties' => [
                ...$this->credentialFields(),
                'host' => Field::text('Host', null, 'web-1'),
                'message' => Field::text('Message'),
                'end' => Field::datetime('Until', 'Empty mutes it until it is unmuted.'),
                'override' => Field::advanced(Field::boolean('Replace an existing mute')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $host = $this->pathId($config, 'host', 'The host');
        $body = array_filter(['message' => $this->text($config, 'message'), 'end' => filled($config['end'] ?? null) ? $this->unixTime($config['end'], 'Until') : null, 'override' => ($config['override'] ?? false) ? true : null], fn (mixed $value): bool => $value !== null);

        return $this->datadog($run, $config, 'POST', "/api/v1/host/{$host}/mute", [], $body);
    }
}
