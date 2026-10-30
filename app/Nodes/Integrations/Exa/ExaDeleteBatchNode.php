<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaDeleteBatchNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_delete_batch';
    }

    public function name(): string
    {
        return 'Exa: Delete Batch';
    }

    public function description(): string
    {
        return 'Deletes a batch that has ended.';
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
        $this->exa($run, $config, 'DELETE', '/batches/'.$this->pathId($config, 'batch_id', 'The batch id'), headers: ['Exa-Beta' => self::BATCH_BETA]);

        return ['deleted' => true];
    }
}
