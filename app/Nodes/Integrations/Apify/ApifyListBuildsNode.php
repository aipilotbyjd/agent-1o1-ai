<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyListBuildsNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_list_builds';
    }

    public function name(): string
    {
        return 'Apify: List Builds';
    }

    public function description(): string
    {
        return 'Lists the builds of all your Actors, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $builds = $this->listItems($run, $config, '/actor-builds');

        return ['builds' => array_map($this->shapeBuild(...), $builds), 'count' => count($builds)];
    }
}
