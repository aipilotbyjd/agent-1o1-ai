<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PinterestDeleteBoardNode extends AbstractPinterestNode
{
    public function type(): string
    {
        return 'pinterest_delete_board';
    }

    public function name(): string
    {
        return 'Pinterest: Delete Board';
    }

    public function description(): string
    {
        return 'Deletes a board and all the pins on it. This cannot be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $response = $this->pinterestResponse($run, $config, 'DELETE', "/boards/{$id}");

        if ($response->status() === 404) {
            return ['deleted' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return ['deleted' => true, 'id' => $id];
    }
}
