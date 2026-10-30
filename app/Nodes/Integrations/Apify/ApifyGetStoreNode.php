<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyGetStoreNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_store';
    }

    public function name(): string
    {
        return 'Apify: Get Key-Value Store';
    }

    public function description(): string
    {
        return 'Reads a key-value store\'s details.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['store_id'],
            'properties' => [
                ...$this->credentialFields(),
                'store_id' => $this->storeField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['store' => $this->apify($run, $config, 'GET', '/key-value-stores/'.$this->pathId($config, 'store_id', 'The store'))];
    }
}
