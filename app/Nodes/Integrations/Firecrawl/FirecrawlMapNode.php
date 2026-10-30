<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlMapNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_map';
    }

    public function name(): string
    {
        return 'Firecrawl: Map Site';
    }

    public function description(): string
    {
        return 'Lists the addresses on a website, most relevant first, optionally around a search term. Fast, and reads no page content.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url'],
            'properties' => [
                ...$this->credentialFields(),
                'url' => Field::text('Website', null, 'https://example.com'),
                'search' => Field::text('Around this term', 'Puts the matching addresses first.'),
                'limit' => Field::integer('Max addresses', null, 5000, 1, 100000),
                'sitemap' => Field::advanced(Field::select('Sitemap', ['include', 'skip', 'only'], null, 'include')),
                'include_subdomains' => Field::advanced(Field::boolean('Include subdomains', null, true)),
                'ignore_query_parameters' => Field::advanced(Field::boolean('Ignore query parameters', null, true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'POST', '/map', $this->withoutEmpty([
            'url' => $this->webUrl($config),
            'search' => $this->textOf($config, 'search'),
            'limit' => $this->intOf($config, 'limit'),
            'sitemap' => $this->textOf($config, 'sitemap'),
            'includeSubdomains' => $this->boolOf($config, 'include_subdomains'),
            'ignoreQueryParameters' => $this->boolOf($config, 'ignore_query_parameters'),
        ]), timeout: 75);
        $links = array_values((array) ($answer['links'] ?? []));

        return ['links' => $links, 'count' => count($links)];
    }
}
