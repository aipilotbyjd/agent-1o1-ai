<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PinterestCreateBoardNode extends AbstractPinterestNode
{
    public function type(): string
    {
        return 'pinterest_create_board';
    }

    public function name(): string
    {
        return 'Pinterest: Create Board';
    }

    public function description(): string
    {
        return 'Creates a board.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'Weeknight dinners'),
                'description' => Field::textarea('Description'),
                'privacy' => Field::advanced(Field::select('Privacy', ['PUBLIC' => 'Public', 'PROTECTED' => 'Protected', 'SECRET' => 'Secret'], null, 'PUBLIC')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $board = $this->pinterest($run, $config, 'POST', '/boards', [], [
            'name' => $this->requiredString($config, 'name', 'The name', 180),
            'description' => $this->optionalString($config, 'description', 'The description', 500),
            'privacy' => in_array($config['privacy'] ?? null, ['PUBLIC', 'PROTECTED', 'SECRET'], true) ? $config['privacy'] : 'PUBLIC',
        ]);

        return $this->shapeBoard($board);
    }
}
