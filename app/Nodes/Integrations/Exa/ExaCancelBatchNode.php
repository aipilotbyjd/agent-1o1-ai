<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaCancelBatchNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_cancel_batch';
    }

    public function name(): string
    {
        return 'Exa: Cancel Batch';
    }

    public function description(): string
    {
        return 'Cancels a batch that is still running.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        return ['batch' => $this->exa($run, $config, 'POST', '/batches/'.$this->pathId($config, 'batch_id', 'The batch id').'/cancel', [], headers: ['Exa-Beta' => self::BATCH_BETA])];
    }
}
