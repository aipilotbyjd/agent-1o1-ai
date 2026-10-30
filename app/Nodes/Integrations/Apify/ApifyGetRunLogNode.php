<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyGetRunLogNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_run_log';
    }

    public function name(): string
    {
        return 'Apify: Get Run Log';
    }

    public function description(): string
    {
        return 'Reads the log of a run.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['run_id'],
            'properties' => [
                ...$this->credentialFields(),
                'run_id' => Field::text('Run id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['log' => $this->apifyResponse($run, $config, 'GET', '/actor-runs/'.$this->pathId($config, 'run_id', 'The run id').'/log')->body()];
    }
}
