<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyGetDatasetSchemaNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_dataset_schema';
    }

    public function name(): string
    {
        return 'Apify: Get Dataset Schema';
    }

    public function description(): string
    {
        return 'Works out a JSON schema from a sample of a dataset\'s items.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['dataset_id'],
            'properties' => [
                ...$this->credentialFields(),
                'dataset_id' => $this->datasetField(),
                'limit' => Field::integer('Items to sample', null, 25, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $items = $this->datasetItems($run, $config, $this->pathId($config, 'dataset_id', 'The dataset'));
        $properties = [];

        foreach ($items as $item) {
            foreach ((array) $item as $field => $value) {
                $properties[$field]['type'] = array_values(array_unique([...($properties[$field]['type'] ?? []), match (true) {
                    is_int($value) => 'integer',
                    is_float($value) => 'number',
                    is_bool($value) => 'boolean',
                    is_array($value) => array_is_list($value) ? 'array' : 'object',
                    $value === null => 'null',
                    default => 'string',
                }]));
            }
        }

        return ['schema' => ['type' => 'object', 'properties' => $properties], 'sampled' => count($items)];
    }
}
