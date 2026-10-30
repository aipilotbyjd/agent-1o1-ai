<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyGetBuildNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_build';
    }

    public function name(): string
    {
        return 'Apify: Get Build';
    }

    public function description(): string
    {
        return 'Reads how a build is going.';
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
        return $this->shapeBuild($this->apify($run, $config, 'GET', '/actor-builds/'.$this->pathId($config, 'build_id', 'The build id')));
    }
}
