<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetSolutionCategoryNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_solution_category';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Solution Category';
    }

    public function description(): string
    {
        return 'Reads a knowledge base category.';
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

        return $this->findOne($run, $config, "/solutions/categories/{$id}", $id);
    }
}
