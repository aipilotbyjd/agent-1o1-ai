<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PinterestCreateSectionNode extends AbstractPinterestNode
{
    public function type(): string
    {
        return 'pinterest_create_section';
    }

    public function name(): string
    {
        return 'Pinterest: Create Board Section';
    }

    public function description(): string
    {
        return 'Adds a section to a board.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['board_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
                'name' => Field::text('Name', null, 'Desserts'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'board_id', 'The board');
        $section = $this->pinterest($run, $config, 'POST', "/boards/{$id}/sections", [], ['name' => $this->requiredString($config, 'name', 'The name', 180)]);

        return ['id' => isset($section['id']) ? (string) $section['id'] : null, 'name' => $section['name'] ?? null, 'board_id' => $id];
    }
}
