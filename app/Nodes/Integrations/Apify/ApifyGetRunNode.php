<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyGetRunNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_run';
    }

    public function name(): string
    {
        return 'Apify: Get Run';
    }

    public function description(): string
    {
        return 'Reads how a run is going, where its results are, and what it cost. Can wait a short while for it to finish.';
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
                'run_id' => Field::text('Run id', 'The id Run Actor or Run Task returned.'),
                'wait_seconds' => Field::advanced(Field::integer('Wait up to (seconds)', 'Leave empty not to wait.', null, 0, self::MAX_WAIT_SECONDS)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $wait = min(self::MAX_WAIT_SECONDS, $this->intOf($config, 'wait_seconds') ?? 0);

        return $this->shapeRun($this->apify($run, $config, 'GET', '/actor-runs/'.$this->pathId($config, 'run_id', 'The run id'), query: ['waitForFinish' => $wait > 0 ? $wait : null], timeout: self::MAX_WAIT_SECONDS + 15));
    }
}
