<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogGetTraceNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_trace';
    }

    public function name(): string
    {
        return 'Datadog: Get Trace';
    }

    public function description(): string
    {
        return 'Gets the spans of one APM trace by its id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['trace_id'],
            'properties' => [
                ...$this->credentialFields(),
                'trace_id' => Field::text('Trace id'),
                'from' => Field::text('From', 'Default now-1d. How far back the trace may be.', 'now-1d'),
                'limit' => Field::integer('Max spans', null, 200, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $trace = $this->requiredString($config, 'trace_id', 'The trace id');

        if (preg_match('/^[A-Fa-f0-9]{1,32}$|^\d{1,40}$/', $trace) !== 1) {
            throw new InvalidArgumentException('The trace id must be a decimal or hexadecimal id.');
        }

        $config = [...$config, 'query' => "trace_id:{$trace}", 'sort' => 'timestamp', 'from' => $this->text($config, 'from') ?? 'now-1d'];
        $body = $this->searchBody($config);
        $result = $this->datadog($run, $config, 'POST', '/api/v2/spans/events/search', [], ['data' => ['type' => 'search_request', 'attributes' => ['filter' => $body['filter'], 'page' => $body['page'], 'sort' => $body['sort']]]]);
        $spans = $this->records($result);

        return ['trace_id' => $trace, 'spans' => $spans, 'count' => count($spans)];
    }
}
