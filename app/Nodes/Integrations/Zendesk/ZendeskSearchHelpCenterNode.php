<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskSearchHelpCenterNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_search_help_center';
    }

    public function name(): string
    {
        return 'Zendesk: Search Help Center';
    }

    public function description(): string
    {
        return 'Searches Help Center articles and community posts together.';
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
        $id = $this->requiredString($config, 'query', 'The search');
        $articles = $this->listAll($run, $config, '/help_center/articles/search', 'results', ['query' => $id], $this->limit($config, 25));
        $posts = $this->listAll($run, $config, '/help_center/community_posts/search', 'results', ['query' => $id], $this->limit($config, 25));

        return ['articles' => $articles, 'posts' => $posts, 'count' => count($articles) + count($posts)];
    }
}
