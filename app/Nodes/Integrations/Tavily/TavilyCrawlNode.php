<?php

namespace App\Nodes\Integrations\Tavily;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TavilyCrawlNode extends AbstractTavilyNode
{
    public function type(): string
    {
        return 'tavily_crawl';
    }

    public function name(): string
    {
        return 'Tavily: Crawl';
    }

    public function description(): string
    {
        return 'Reads a website from a starting address, following links, and returns each page\'s content. Bounded to a minute and a half.';
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
                ...$this->walkFields(),
                'format' => Field::select('Format', ['markdown', 'text'], null, 'markdown'),
                'extract_depth' => Field::advanced(Field::select('Depth', ['basic', 'advanced'], null, 'basic')),
                'chunks_per_source' => Field::advanced(Field::integer('Passages per page', 'Used with Look for.', null, 1, 5)),
                'include_images' => Field::advanced(Field::boolean('Include images')),
                'include_favicon' => Field::advanced(Field::boolean('Include site icons')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->tavily($run, $config, 'POST', '/crawl', $this->withoutEmpty([
            ...$this->walkBody($config),
            'format' => $this->textOf($config, 'format'),
            'extract_depth' => $this->textOf($config, 'extract_depth'),
            'chunks_per_source' => $this->intOf($config, 'chunks_per_source'),
            'include_images' => $this->boolOf($config, 'include_images'),
            'include_favicon' => $this->boolOf($config, 'include_favicon'),
            'include_usage' => true,
        ]), $this->timeoutSeconds($config) + 15);
        $results = array_values((array) ($result['results'] ?? []));

        return ['base_url' => $result['base_url'] ?? null, 'results' => $results, 'count' => count($results), 'credits' => data_get($result, 'usage.credits'), 'request_id' => $result['request_id'] ?? null];
    }
}
