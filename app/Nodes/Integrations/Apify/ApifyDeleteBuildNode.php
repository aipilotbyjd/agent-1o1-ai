<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyDeleteBuildNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_delete_build';
    }

    public function name(): string
    {
        return 'Apify: Delete Build';
    }

    public function description(): string
    {
        return 'Deletes a build for good.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->apify($run, $config, 'DELETE', '/actor-builds/'.$this->pathId($config, 'build_id', 'The build id'));

        return ['deleted' => true];
    }
}
