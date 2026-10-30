<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyCreateDatasetNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_create_dataset';
    }

    public function name(): string
    {
        return 'Apify: Create Dataset';
    }

    public function description(): string
    {
        return 'Creates a named dataset, or returns the one already called that.';
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
        return ['dataset' => $this->apify($run, $config, 'POST', '/datasets', [], ['name' => $this->requiredString($config, 'name', 'The name')])];
    }
}
