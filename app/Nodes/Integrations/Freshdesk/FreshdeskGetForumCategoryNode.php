<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetForumCategoryNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_forum_category';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Forum Category';
    }

    public function description(): string
    {
        return 'Reads a forum category.';
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
                'category_id' => Field::dynamic('Category', 'freshdesk.forum_categories'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'category_id', 'The category');

        return $this->findOne($run, $config, "/discussions/categories/{$id}", $id);
    }
}
