<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyResurrectRunNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_resurrect_run';
    }

    public function name(): string
    {
        return 'Apify: Resurrect Run';
    }

    public function description(): string
    {
        return 'Starts a finished run again from where it stopped, keeping its storage.';
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
                'run_timeout' => Field::advanced(Field::integer('Run time limit (seconds)', null, null, 0)),
                'memory' => Field::advanced(Field::integer('Memory (MB)', null, null, 128)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeRun($this->apify($run, $config, 'POST', '/actor-runs/'.$this->pathId($config, 'run_id', 'The run id').'/resurrect', [], ['timeout' => $this->intOf($config, 'run_timeout'), 'memory' => $this->intOf($config, 'memory')]));
    }
}
