<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateSolutionArticleNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_solution_article';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Solution Article';
    }

    public function description(): string
    {
        return 'Writes a knowledge base article, as a draft unless published.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['folder_id', 'title', 'description'],
            'properties' => [
                ...$this->credentialFields(),
                'folder_id' => Field::text('Folder id'),
                'title' => Field::text('Title'),
                'description' => Field::textarea('Body', 'HTML is kept.'),
                'status' => Field::advanced(Field::select('State', ['1' => 'Draft', '2' => 'Published'], null, '1')),
                'tags' => Field::advanced(Field::text('Tags', 'Separated by commas.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'folder_id', 'The folder');

        return $this->freshdesk($run, $config, 'POST', "/solutions/folders/{$id}/articles", [], $this->present(['title' => $this->requiredString($config, 'title', 'The title'), 'description' => $this->requiredString($config, 'description', 'The body'), 'status' => $this->number($config, 'status') ?? 1, 'tags' => $this->words($config, 'tags')]));
    }
}
