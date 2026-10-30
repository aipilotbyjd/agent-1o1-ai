<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaGetBatchNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_get_batch';
    }

    public function name(): string
    {
        return 'Exa: Get Batch';
    }

    public function description(): string
    {
        return 'Reads how a batch is going, and where its results are.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['batch_id'],
            'properties' => [
                ...$this->credentialFields(),
                'batch_id' => Field::text('Batch id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['batch' => $this->exa($run, $config, 'GET', '/batches/'.$this->pathId($config, 'batch_id', 'The batch id'), headers: ['Exa-Beta' => self::BATCH_BETA])];
    }
}
