<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyDeleteStoreRecordNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_delete_store_record';
    }

    public function name(): string
    {
        return 'Apify: Delete Key-Value Store Record';
    }

    public function description(): string
    {
        return 'Deletes a record from a key-value store.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['store_id', 'key'],
            'properties' => [
                ...$this->credentialFields(),
                'store_id' => $this->storeField(),
                'key' => Field::text('Key'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->apify($run, $config, 'DELETE', '/key-value-stores/'.$this->pathId($config, 'store_id', 'The store').'/records/'.$this->pathId($config, 'key', 'The key'));

        return ['deleted' => true];
    }
}
