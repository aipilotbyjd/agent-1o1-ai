<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetProductNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_product';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Product';
    }

    public function description(): string
    {
        return 'Reads a product by id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['product_id'],
            'properties' => [
                ...$this->credentialFields(),
                'product_id' => Field::dynamic('Product', 'freshdesk.products'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'product_id', 'The product');

        return $this->findOne($run, $config, "/products/{$id}", $id);
    }
}
