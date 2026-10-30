<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteSolutionArticleNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_solution_article';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Solution Article';
    }

    public function description(): string
    {
        return 'Deletes a knowledge base article.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['article_id'],
            'properties' => [
                ...$this->credentialFields(),
                'article_id' => Field::text('Article id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'article_id', 'The article');

        return $this->destroy($run, $config, "/solutions/articles/{$id}", $id);
    }
}
