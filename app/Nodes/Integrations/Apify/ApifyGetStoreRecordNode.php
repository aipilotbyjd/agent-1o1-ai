<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyGetStoreRecordNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_store_record';
    }

    public function name(): string
    {
        return 'Apify: Get Key-Value Store Record';
    }

    public function description(): string
    {
        return 'Reads a record from a key-value store. A run\'s output is often under the key OUTPUT.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['store_id', 'key'],
            'properties' => [
                ...$this->credentialFields(),
                'store_id' => $this->storeField(),
                'key' => Field::text('Key', null, 'OUTPUT'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->apifyResponse($run, $config, 'GET', '/key-value-stores/'.$this->pathId($config, 'store_id', 'The store').'/records/'.$this->pathId($config, 'key', 'The key'));
        $json = $response->json();

        return ['key' => $config['key'], 'value' => $json ?? $response->body(), 'content_type' => $response->header('Content-Type')];
    }
}
