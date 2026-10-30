<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloSearchNewsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_search_news';
    }

    public function name(): string
    {
        return 'Apollo: Search News Articles';
    }

    public function description(): string
    {
        return 'Finds news about companies by their Apollo ids. Uses Apollo credits.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['organization_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'organization_ids' => Field::list('Organization ids'),
                'categories' => Field::list('Categories', 'Like hires, investment or contract.'),
                'published_after' => Field::text('Published after', 'YYYY-MM-DD.'),
                'published_before' => Field::text('Published before', 'YYYY-MM-DD.'),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = ['organization_ids' => $this->requiredList($config, 'organization_ids', 'The organization ids'), 'categories' => $this->stringList($config, 'categories'), 'published_at[min]' => $config['published_after'] ?? null, 'published_at[max]' => $config['published_before'] ?? null, ...$this->paging($config)];

        return $this->listResult($this->apollo($run, $config, 'POST', '/news_articles/search', $query), 'news_articles');
    }
}
