<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PinterestGetPinNode extends AbstractPinterestNode
{
    public function type(): string
    {
        return 'pinterest_get_pin';
    }

    public function name(): string
    {
        return 'Pinterest: Get Pin';
    }

    public function description(): string
    {
        return 'Reads a pin. Says whether it exists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['pin_id'],
            'properties' => [
                ...$this->credentialFields(),
                'pin_id' => Field::text('Pin id', 'An id from a previous step (a long number).'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'pin_id', 'The pin');
        $response = $this->pinterestResponse($run, $config, 'GET', "/pins/{$id}");

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return [...$this->shapePin((array) $response->json()), 'found' => true];
    }
}
