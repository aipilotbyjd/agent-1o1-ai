<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyListActorsNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_list_actors';
    }

    public function name(): string
    {
        return 'Apify: List Actors';
    }

    public function description(): string
    {
        return 'Lists your Actors, and optionally those you have used.';
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
                'only_mine' => Field::boolean('Only ones I created', null, true),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $actors = $this->listItems($run, $config, '/actors', ['my' => $this->boolOf($config, 'only_mine')]);

        return ['actors' => $actors, 'count' => count($actors)];
    }
}
