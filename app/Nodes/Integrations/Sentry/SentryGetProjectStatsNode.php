<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetProjectStatsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_project_stats';
    }

    public function name(): string
    {
        return 'Sentry: Get Project Stats';
    }

    public function description(): string
    {
        return 'Gets a project\'s event counts over time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'project' => $this->projectField(),
                'stat' => Field::select('Count', ['received' => 'Received', 'rejected' => 'Rejected', 'blacklisted' => 'Filtered', 'generated' => 'Generated'], null, 'received'),
                'resolution' => Field::advanced(Field::select('Resolution', ['10s' => '10 seconds', '1h' => '1 hour', '1d' => '1 day'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['points' => $this->sentry($run, $config, 'GET', '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/stats/', ['stat' => $this->text($config, 'stat'), 'resolution' => $this->text($config, 'resolution')])];
    }
}
