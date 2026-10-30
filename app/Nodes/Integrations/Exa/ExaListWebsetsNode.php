<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaListWebsetsNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_list_websets';
    }

    public function name(): string
    {
        return 'Exa: List Websets';
    }

    public function description(): string
    {
        return 'Lists the team\'s websets.';
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
                'search' => Field::text('Search'),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeList($this->exa($run, $config, 'GET', self::WEBSETS.'/websets', query: [...$this->pageQuery($config), 'search' => $this->textOf($config, 'search')]));
    }
}
