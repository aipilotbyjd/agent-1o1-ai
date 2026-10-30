<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PinterestListSectionsNode extends AbstractPinterestNode
{
    public function type(): string
    {
        return 'pinterest_list_sections';
    }

    public function name(): string
    {
        return 'Pinterest: List Board Sections';
    }

    public function description(): string
    {
        return 'Lists the sections of a board.';
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
        $sections = $this->listAll($run, $config, "/boards/{$id}/sections", [], 250);

        return ['sections' => array_map(fn (array $section): array => ['id' => isset($section['id']) ? (string) $section['id'] : null, 'name' => $section['name'] ?? null], $sections), 'count' => count($sections)];
    }
}
