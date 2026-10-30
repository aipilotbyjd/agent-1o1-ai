<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaListMonitorsNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_list_monitors';
    }

    public function name(): string
    {
        return 'Exa: List Monitors';
    }

    public function description(): string
    {
        return 'Lists the team\'s search monitors.';
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
                'status' => Field::select('Status', ['active', 'paused'], 'Leave empty for all.'),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeList($this->exa($run, $config, 'GET', '/monitors', query: [...$this->pageQuery($config), 'status' => $this->textOf($config, 'status')]));
    }
}
