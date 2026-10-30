<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsContentTopicsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_content_topics';
    }

    public function name(): string
    {
        return 'Ahrefs: Content Helper Topics';
    }

    public function description(): string
    {
        return 'The core topics covered by the pages ranking for a keyword.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['keyword', 'country'],
            'properties' => [
                ...$this->credentialFields(),
                'keyword' => Field::text('Keyword', 'The keyword to analyse the SERP for.'),
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'top_competitors' => Field::advanced(Field::integer('Top competitors', 'How many of the top-ranking pages to analyse.')),
                'target_url' => Field::advanced(Field::text('Target url', '(500 units) A page of your own to score against the same topics, for example a draft you are writing or a page you want to improve.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/content-helper/topics',
            'items' => 'topics',
            'query' => ['keyword', 'country', 'top_competitors', 'target_url'],
        ];
    }
}
