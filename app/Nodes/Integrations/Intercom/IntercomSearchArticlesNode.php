<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomSearchArticlesNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_search_articles';
    }

    public function name(): string
    {
        return 'Intercom: Search Articles';
    }

    public function description(): string
    {
        return 'Searches Help Center articles by a phrase.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['phrase'],
            'properties' => [
                ...$this->credentialFields(),
                'phrase' => Field::text('Search', null, 'reset password'),
                'state' => Field::advanced(Field::select('State', ['published' => 'Published', 'draft' => 'Draft', 'all' => 'All'])),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->pagedAll($run, $config, '/articles/search', 'data.articles', ['phrase' => $this->requiredString($config, 'phrase', 'The search'), 'state' => $this->text($config, 'state')], $this->limit($config, 50));

        return ['articles' => $rows, 'count' => count($rows)];
    }
}
