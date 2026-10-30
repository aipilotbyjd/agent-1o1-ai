<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateSolutionFolderNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_solution_folder';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Solution Folder';
    }

    public function description(): string
    {
        return 'Adds a folder to a knowledge base category.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['category_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'category_id' => Field::dynamic('Category', 'freshdesk.solution_categories'),
                'name' => Field::text('Name'),
                'description' => Field::advanced(Field::textarea('Description')),
                'visibility' => Field::advanced(Field::select('Visible to', ['1' => 'Everyone', '2' => 'Logged in users', '3' => 'Agents', '4' => 'Selected companies'], null, '1')),
                'parent_folder_id' => Field::advanced(Field::text('Parent folder id')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'category_id', 'The category');

        return $this->freshdesk($run, $config, 'POST', "/solutions/categories/{$id}/folders", [], $this->present(['name' => $this->requiredString($config, 'name', 'The name'), 'description' => $this->text($config, 'description'), 'visibility' => $this->number($config, 'visibility') ?? 1, 'parent_folder_id' => $this->optionalId($config, 'parent_folder_id', 'The parent folder')]));
    }
}
