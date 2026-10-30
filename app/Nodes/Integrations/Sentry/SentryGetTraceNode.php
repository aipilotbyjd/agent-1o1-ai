<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetTraceNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_trace';
    }

    public function name(): string
    {
        return 'Sentry: Get Trace';
    }

    public function description(): string
    {
        return 'Gets a distributed trace with its spans.';
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
                'organization' => $this->organizationField(),
                'trace_id' => Field::text('Trace id'),
                'stats_period' => Field::advanced(Field::text('Period', 'How far back to look. Default 14d.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $trace = $this->find($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/trace/'.$this->pathId($config, 'trace_id', 'The trace id').'/', ['statsPeriod' => $this->text($config, 'stats_period') ?? '14d']);

        return $trace === null ? ['found' => false] : ['found' => true, 'spans' => $trace];
    }
}
