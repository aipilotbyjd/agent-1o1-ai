<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogSendLogsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_send_logs';
    }

    public function name(): string
    {
        return 'Datadog: Send Log';
    }

    public function description(): string
    {
        return 'Sends a log entry to Datadog.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message'],
            'properties' => [
                ...$this->credentialFields(),
                'message' => Field::textarea('Message'),
                'service' => Field::text('Service'),
                'hostname' => Field::text('Host'),
                'source' => Field::text('Source', 'The technology, like nginx.'),
                'tags' => Field::list('Tags', 'Like env:prod.'),
                'attributes' => Field::advanced(Field::json('More attributes', 'Extra fields to store on the log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $entry = array_filter([
            'message' => $this->requiredString($config, 'message', 'The message'),
            'service' => $this->text($config, 'service'),
            'hostname' => $this->text($config, 'hostname'),
            'ddsource' => $this->text($config, 'source'),
            'ddtags' => implode(',', $this->listOf($config, 'tags')) ?: null,
            ...((array) ($this->jsonOf($config, 'attributes', 'Attributes') ?? [])),
        ], fn (mixed $value): bool => $value !== null);

        $this->datadog($run, $config, 'POST', '/api/v2/logs', [], [$entry], 'http-intake.logs');

        return ['accepted' => true];
    }
}
