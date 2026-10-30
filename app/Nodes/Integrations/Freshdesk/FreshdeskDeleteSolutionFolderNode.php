<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteSolutionFolderNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_solution_folder';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Solution Folder';
    }

    public function description(): string
    {
        return 'Deletes a knowledge base folder with its articles.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        return $this->destroy($run, $config, "/solutions/folders/{$id}", $id);
    }
}
