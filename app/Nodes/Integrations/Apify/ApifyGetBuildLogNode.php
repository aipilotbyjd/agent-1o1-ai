<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyGetBuildLogNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_build_log';
    }

    public function name(): string
    {
        return 'Apify: Get Build Log';
    }

    public function description(): string
    {
        return 'Reads the log of a build.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['build_id'],
            'properties' => [
                ...$this->credentialFields(),
                'build_id' => Field::text('Build id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['log' => $this->apifyResponse($run, $config, 'GET', '/actor-builds/'.$this->pathId($config, 'build_id', 'The build id').'/log')->body()];
    }
}
