<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteForumCategoryNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_forum_category';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Forum Category';
    }

    public function description(): string
    {
        return 'Deletes a forum category with its forums and topics.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        return $this->destroy($run, $config, "/discussions/categories/{$id}", $id);
    }
}
