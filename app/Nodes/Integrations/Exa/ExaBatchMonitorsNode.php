<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaBatchMonitorsNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_batch_monitors';
    }

    public function name(): string
    {
        return 'Exa: Change Monitors In Bulk';
    }

    public function description(): string
    {
        return 'Deletes, pauses or unpauses the search monitors that match a filter. Can show what it would do first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['action', 'filter'],
            'properties' => [
                ...$this->credentialFields(),
                'action' => Field::select('Action', ['pause', 'unpause', 'delete']),
                'filter' => Field::json('Filter', 'Which monitors, as in Exa\'s docs.'),
                'dry_run' => Field::boolean('Only show what would change', null, true),
                'limit' => Field::advanced(Field::integer('At most', null, null, 1)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['result' => $this->exa($run, $config, 'POST', '/monitors/batch', $this->withoutEmpty(['action' => $this->requiredString($config, 'action', 'The action'), 'filter' => $this->decoded($config['filter'] ?? null, 'The filter') ?? [], 'dry_run' => $this->boolOf($config, 'dry_run'), 'limit' => $this->intOf($config, 'limit')]))];
    }
}
