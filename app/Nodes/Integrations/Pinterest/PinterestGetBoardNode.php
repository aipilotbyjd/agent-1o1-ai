<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PinterestGetBoardNode extends AbstractPinterestNode
{
    public function type(): string
    {
        return 'pinterest_get_board';
    }

    public function name(): string
    {
        return 'Pinterest: Get Board';
    }

    public function description(): string
    {
        return 'Reads a board. Says whether it exists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['board_id'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'board_id', 'The board');
        $response = $this->pinterestResponse($run, $config, 'GET', "/boards/{$id}");

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return [...$this->shapeBoard((array) $response->json()), 'found' => true];
    }
}
