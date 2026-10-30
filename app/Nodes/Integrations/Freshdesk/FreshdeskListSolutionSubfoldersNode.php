<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskListSolutionSubfoldersNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_solution_subfolders';
    }

    public function name(): string
    {
        return 'Freshdesk: List Solution Subfolders';
    }

    public function description(): string
    {
        return 'Lists the folders inside a knowledge base folder.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['folder_id'],
            'properties' => [
                ...$this->credentialFields(),
                'folder_id' => Field::text('Folder id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'folder_id', 'The folder');
        $rows = $this->freshdesk($run, $config, 'GET', "/solutions/folders/{$id}/subfolders");

        return ['folders' => array_values($rows), 'count' => count($rows)];
    }
}
