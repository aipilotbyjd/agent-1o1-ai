<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskSearchArticlesNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_search_articles';
    }

    public function name(): string
    {
        return 'Zendesk: Search Articles';
    }

    public function description(): string
    {
        return 'Searches Help Center articles.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Search', null, 'reset password'),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/help_center/articles/search', 'results', ['query' => $this->requiredString($config, 'query', 'The search')], null, 25);
    }
}
