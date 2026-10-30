<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyRebootRunNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_reboot_run';
    }

    public function name(): string
    {
        return 'Apify: Reboot Run';
    }

    public function description(): string
    {
        return 'Restarts the container of a running Actor run.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        return $this->shapeRun($this->apify($run, $config, 'POST', '/actor-runs/'.$this->pathId($config, 'run_id', 'The run id').'/reboot', []));
    }
}
