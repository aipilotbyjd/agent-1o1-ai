<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ApifyAddDatasetItemsNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_add_dataset_items';
    }

    public function name(): string
    {
        return 'Apify: Add Dataset Items';
    }

    public function description(): string
    {
        return 'Stores one item or a list of items in a dataset.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['dataset_id', 'items'],
            'properties' => [
                ...$this->credentialFields(),
                'dataset_id' => $this->datasetField(),
                'items' => Field::json('Items', 'An object, or a list of objects.', 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $items = $this->decoded($config['items'] ?? null, 'The items');

        if ($items === null) {
            throw new InvalidArgumentException('The items are required.');
        }

        $this->apify($run, $config, 'POST', '/datasets/'.$this->pathId($config, 'dataset_id', 'The dataset').'/items', $items);

        return ['added' => array_is_list($items) ? count($items) : 1];
    }
}
