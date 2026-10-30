<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaCreateBatchNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_create_batch';
    }

    public function name(): string
    {
        return 'Exa: Create Batch';
    }

    public function description(): string
    {
        return 'Submits many searches or research runs to run together, and returns the batch id at once.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['requests'],
            'properties' => [
                ...$this->credentialFields(),
                'requests' => Field::json('Requests', 'A list of {"customId": "...", "method": "POST", "url": "/search", "body": {...}}, as in Exa\'s docs.', 'array'),
                'metadata' => Field::advanced(Field::keyValue('Metadata')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['batch' => $this->exa($run, $config, 'POST', '/batches', $this->withoutEmpty(['requests' => $this->decoded($config['requests'] ?? null, 'The requests'), 'metadata' => $this->decoded($config['metadata'] ?? null, 'The metadata')]), headers: ['Exa-Beta' => self::BATCH_BETA])];
    }
}
