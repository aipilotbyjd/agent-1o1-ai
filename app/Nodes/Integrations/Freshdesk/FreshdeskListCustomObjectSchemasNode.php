<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskListCustomObjectSchemasNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_custom_object_schemas';
    }

    public function name(): string
    {
        return 'Freshdesk: List Custom Objects';
    }

    public function description(): string
    {
        return 'Lists the custom object schemas. Needs a plan with custom objects.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->freshdesk($run, $config, 'GET', '/custom_objects/schemas');
        $rows = array_values((array) ($body['schemas'] ?? $body));

        return ['schemas' => $rows, 'count' => count($rows)];
    }
}
