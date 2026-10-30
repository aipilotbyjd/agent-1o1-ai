<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateForumNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_forum';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Forum';
    }

    public function description(): string
    {
        return 'Adds a forum to a category.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['category_id', 'name', 'forum_type', 'forum_visibility'],
            'properties' => [
                ...$this->credentialFields(),
                'category_id' => Field::dynamic('Category', 'freshdesk.forum_categories'),
                'name' => Field::text('Name'),
                'forum_type' => Field::select('Type', ['1' => 'How-to', '2' => 'Ideas', '3' => 'Problems', '4' => 'Announcements'], null, '1'),
                'forum_visibility' => Field::select('Visible to', ['1' => 'Everyone', '2' => 'Logged in users', '3' => 'Agents', '4' => 'Selected companies'], null, '1'),
                'description' => Field::advanced(Field::textarea('Description')),
                'company_ids' => Field::advanced(Field::text('Company ids', 'Separated by commas, for selected companies.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'category_id', 'The category');

        return $this->freshdesk($run, $config, 'POST', "/discussions/categories/{$id}/forums", [], $this->present(['name' => $this->requiredString($config, 'name', 'The name'), 'forum_type' => $this->number($config, 'forum_type') ?? 1, 'forum_visibility' => $this->number($config, 'forum_visibility') ?? 1, 'description' => $this->text($config, 'description'), 'company_ids' => $this->idList($config, 'company_ids', 'The companies', false)]));
    }
}
