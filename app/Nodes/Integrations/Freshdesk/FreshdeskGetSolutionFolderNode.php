<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetSolutionFolderNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_solution_folder';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Solution Folder';
    }

    public function description(): string
    {
        return 'Reads a knowledge base folder.';
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

        return $this->findOne($run, $config, "/solutions/folders/{$id}", $id);
    }
}
