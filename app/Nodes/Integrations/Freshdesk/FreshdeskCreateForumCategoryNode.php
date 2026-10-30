<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateForumCategoryNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_forum_category';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Forum Category';
    }

    public function description(): string
    {
        return 'Adds a forum category.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
                'description' => Field::advanced(Field::textarea('Description')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->freshdesk($run, $config, 'POST', '/discussions/categories', [], $this->present(['name' => $this->requiredString($config, 'name', 'The name'), 'description' => $this->text($config, 'description')]));
    }
}
