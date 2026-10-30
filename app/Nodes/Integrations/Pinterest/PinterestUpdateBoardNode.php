<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class PinterestUpdateBoardNode extends AbstractPinterestNode
{
    public function type(): string
    {
        return 'pinterest_update_board';
    }

    public function name(): string
    {
        return 'Pinterest: Update Board';
    }

    public function description(): string
    {
        return 'Changes a board. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['board_id'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
                'name' => Field::text('Name'),
                'description' => Field::textarea('Description'),
                'privacy' => Field::advanced(Field::select('Privacy', ['PUBLIC' => 'Public', 'PROTECTED' => 'Protected', 'SECRET' => 'Secret'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'board_id', 'The board');
        $body = array_filter([
            'name' => $this->optionalString($config, 'name', 'The name', 180),
            'description' => $this->optionalString($config, 'description', 'The description', 500),
            'privacy' => in_array($config['privacy'] ?? null, ['PUBLIC', 'PROTECTED', 'SECRET'], true) ? $config['privacy'] : null,
        ], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeBoard($this->pinterest($run, $config, 'PATCH', "/boards/{$id}", [], $body));
    }
}
