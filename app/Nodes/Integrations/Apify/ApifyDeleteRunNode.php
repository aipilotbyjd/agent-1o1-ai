<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyDeleteRunNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_delete_run';
    }

    public function name(): string
    {
        return 'Apify: Delete Run';
    }

    public function description(): string
    {
        return 'Deletes a finished run for good.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->apify($run, $config, 'DELETE', '/actor-runs/'.$this->pathId($config, 'run_id', 'The run id'));

        return ['deleted' => true];
    }
}
