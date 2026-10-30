<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PinterestDeletePinNode extends AbstractPinterestNode
{
    public function type(): string
    {
        return 'pinterest_delete_pin';
    }

    public function name(): string
    {
        return 'Pinterest: Delete Pin';
    }

    public function description(): string
    {
        return 'Deletes a pin you created.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $response = $this->pinterestResponse($run, $config, 'DELETE', "/pins/{$id}");

        if ($response->status() === 404) {
            return ['deleted' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return ['deleted' => true, 'id' => $id];
    }
}
