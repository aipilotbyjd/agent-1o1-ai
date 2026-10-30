<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskListForumsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_forums';
    }

    public function name(): string
    {
        return 'Freshdesk: List Forums';
    }

    public function description(): string
    {
        return 'Lists the forums of a category.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'category_id', 'The category');

        return $this->listing($run, $config, "/discussions/categories/{$id}/forums", 'forums');
    }
}
