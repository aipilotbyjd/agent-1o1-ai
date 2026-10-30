<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyCreateStoreNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_create_store';
    }

    public function name(): string
    {
        return 'Apify: Create Key-Value Store';
    }

    public function description(): string
    {
        return 'Creates a named key-value store, or returns the one already called that.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['store' => $this->apify($run, $config, 'POST', '/key-value-stores', [], ['name' => $this->requiredString($config, 'name', 'The name')])];
    }
}
