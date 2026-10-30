<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskSearchSolutionsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_search_solutions';
    }

    public function name(): string
    {
        return 'Freshdesk: Search Solutions';
    }

    public function description(): string
    {
        return 'Searches the knowledge base\'s articles.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['term'],
            'properties' => [
                ...$this->credentialFields(),
                'term' => Field::text('Search', null, 'reset password'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->freshdesk($run, $config, 'GET', '/search/solutions', ['term' => $this->requiredString($config, 'term', 'The search')]);

        return ['articles' => array_values($rows), 'count' => count($rows)];
    }
}
