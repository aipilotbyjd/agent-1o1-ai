<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifySetStoreRecordNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_set_store_record';
    }

    public function name(): string
    {
        return 'Apify: Set Key-Value Store Record';
    }

    public function description(): string
    {
        return 'Creates or replaces a record in a key-value store.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['store_id', 'key', 'value'],
            'properties' => [
                ...$this->credentialFields(),
                'store_id' => $this->storeField(),
                'key' => Field::text('Key'),
                'value' => Field::json('Value', 'The JSON to store.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->apify($run, $config, 'PUT', '/key-value-stores/'.$this->pathId($config, 'store_id', 'The store').'/records/'.$this->pathId($config, 'key', 'The key'), $this->decoded($config['value'] ?? null, 'The value') ?? []);

        return ['saved' => true, 'key' => $config['key']];
    }
}
