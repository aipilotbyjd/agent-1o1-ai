<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskListSolutionArticlesNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_solution_articles';
    }

    public function name(): string
    {
        return 'Freshdesk: List Solution Articles';
    }

    public function description(): string
    {
        return 'Lists the articles of a knowledge base folder.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'folder_id', 'The folder');

        return $this->listing($run, $config, "/solutions/folders/{$id}/articles", 'articles');
    }
}
