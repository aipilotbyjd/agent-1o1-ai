<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyAbortRunNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_abort_run';
    }

    public function name(): string
    {
        return 'Apify: Abort Run';
    }

    public function description(): string
    {
        return 'Stops a running Actor or task run.';
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
                'gracefully' => Field::boolean('Gracefully', 'Gives it 30 seconds to save its state first.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeRun($this->apify($run, $config, 'POST', '/actor-runs/'.$this->pathId($config, 'run_id', 'The run id').'/abort', [], ['gracefully' => $this->boolOf($config, 'gracefully')]));
    }
}
