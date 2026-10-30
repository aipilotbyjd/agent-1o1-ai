<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateSolutionArticleNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_solution_article';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Solution Article';
    }

    public function description(): string
    {
        return 'Changes a knowledge base article. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['article_id'],
            'properties' => [
                ...$this->credentialFields(),
                'article_id' => Field::text('Article id'),
                'title' => Field::advanced(Field::text('Title')),
                'description' => Field::advanced(Field::textarea('Body', 'HTML is kept.')),
                'status' => Field::advanced(Field::select('State', ['1' => 'Draft', '2' => 'Published'])),
                'agent_id' => Field::advanced(Field::dynamic('Author', 'freshdesk.agents')),
                'tags' => Field::advanced(Field::text('Tags', 'Separated by commas.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'article_id', 'The article');
        $body = $this->present(['title' => $this->text($config, 'title'), 'description' => $this->text($config, 'description'), 'status' => $this->number($config, 'status'), 'agent_id' => $this->optionalId($config, 'agent_id', 'The author'), 'tags' => $this->words($config, 'tags')]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->freshdesk($run, $config, 'PUT', "/solutions/articles/{$id}", [], $body);
    }
}
