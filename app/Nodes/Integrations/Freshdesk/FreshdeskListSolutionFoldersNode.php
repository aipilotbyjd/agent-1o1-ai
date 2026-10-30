<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskListSolutionFoldersNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_solution_folders';
    }

    public function name(): string
    {
        return 'Freshdesk: List Solution Folders';
    }

    public function description(): string
    {
        return 'Lists the folders of a knowledge base category.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['category_id'],
            'properties' => [
                ...$this->credentialFields(),
                'category_id' => Field::dynamic('Category', 'freshdesk.solution_categories'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'category_id', 'The category');
        $rows = $this->freshdesk($run, $config, 'GET', "/solutions/categories/{$id}/folders");

        return ['folders' => array_values($rows), 'count' => count($rows)];
    }
}
