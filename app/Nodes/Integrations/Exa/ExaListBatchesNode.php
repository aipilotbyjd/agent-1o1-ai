<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExaListBatchesNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_list_batches';
    }

    public function name(): string
    {
        return 'Exa: List Batches';
    }

    public function description(): string
    {
        return 'Lists the team\'s batches, newest first.';
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
        return $this->shapeList($this->exa($run, $config, 'GET', '/batches', query: $this->pageQuery($config), headers: ['Exa-Beta' => self::BATCH_BETA]));
    }
}
