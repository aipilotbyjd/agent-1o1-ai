<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateSolutionCategoryNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_solution_category';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Solution Category';
    }

    public function description(): string
    {
        return 'Changes a knowledge base category.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['category_id'],
            'properties' => [
                ...$this->credentialFields(),
                'category_id' => Field::dynamic('Category', 'freshdesk.solution_categories'),
                'name' => Field::advanced(Field::text('Name')),
                'description' => Field::advanced(Field::textarea('Description')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'category_id', 'The category');
        $body = $this->present(['name' => $this->text($config, 'name'), 'description' => $this->text($config, 'description')]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->freshdesk($run, $config, 'PUT', "/solutions/categories/{$id}", [], $body);
    }
}
