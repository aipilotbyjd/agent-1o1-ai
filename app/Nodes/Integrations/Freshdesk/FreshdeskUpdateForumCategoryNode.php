<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateForumCategoryNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_forum_category';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Forum Category';
    }

    public function description(): string
    {
        return 'Changes a forum category.';
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
                'category_id' => Field::dynamic('Category', 'freshdesk.forum_categories'),
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

        return $this->freshdesk($run, $config, 'PUT', "/discussions/categories/{$id}", [], $body);
    }
}
