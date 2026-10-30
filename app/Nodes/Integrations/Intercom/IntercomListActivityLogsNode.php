<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class IntercomListActivityLogsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_activity_logs';
    }

    public function name(): string
    {
        return 'Intercom: List Activity Logs';
    }

    public function description(): string
    {
        return 'Lists what teammates did in the workspace since a time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'since' => Field::datetime('Since', 'Empty is the last day.'),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = filled($config['since'] ?? null) ? Carbon::parse((string) $config['since']) : now()->subDay();
        $rows = $this->pagedAll($run, $config, '/admins/activity_logs', 'activity_logs', ['created_at_after' => $since->getTimestamp()], $this->limit($config, 100));

        return ['activity_logs' => $rows, 'count' => count($rows)];
    }
}
