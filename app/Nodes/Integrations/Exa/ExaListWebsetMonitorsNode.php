<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExaListWebsetMonitorsNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_list_webset_monitors';
    }

    public function name(): string
    {
        return 'Exa: List Webset Monitors';
    }

    public function description(): string
    {
        return 'Lists the team\'s webset monitors.';
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
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeList($this->exa($run, $config, 'GET', self::WEBSETS.'/monitors', query: $this->pageQuery($config)));
    }
}
