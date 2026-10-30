<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyDeleteStoreNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_delete_store';
    }

    public function name(): string
    {
        return 'Apify: Delete Key-Value Store';
    }

    public function description(): string
    {
        return 'Deletes a key-value store and its records for good.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->apify($run, $config, 'DELETE', '/key-value-stores/'.$this->pathId($config, 'store_id', 'The store'));

        return ['deleted' => true];
    }
}
