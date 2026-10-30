<?php

namespace App\Nodes\Integrations\Tavily;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TavilyExtractNode extends AbstractTavilyNode
{
    public function type(): string
    {
        return 'tavily_extract';
    }

    public function name(): string
    {
        return 'Tavily: Extract';
    }

    public function description(): string
    {
        return 'Reads the content of up to 20 pages as markdown or text, optionally only what is about a question.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['urls'],
            'properties' => [
                ...$this->credentialFields(),
                'urls' => Field::list('Pages', 'Up to 20.', 'https://example.com'),
                'query' => Field::text('About', 'Reorders the content around this.'),
                'format' => Field::select('Format', ['markdown', 'text'], null, 'markdown'),
                'extract_depth' => Field::select('Depth', ['basic', 'advanced'], 'Advanced also reads tables and embedded content, and costs more.', 'basic'),
                'chunks_per_source' => Field::advanced(Field::integer('Passages per page', 'Used with About.', null, 1, 5)),
                'include_images' => Field::advanced(Field::boolean('Include images')),
                'include_favicon' => Field::advanced(Field::boolean('Include site icons')),
                'timeout' => Field::advanced(Field::integer('Timeout (seconds)', null, 30, 1, 60)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $urls = $this->webUrls($config);

        if (count($urls) > 20) {
            throw new InvalidArgumentException('Tavily reads at most 20 pages at a time.');
        }

        $timeout = $this->limit($config, 30, 60, 'timeout');
        $result = $this->tavily($run, $config, 'POST', '/extract', $this->withoutEmpty([
            'urls' => $urls,
            'query' => $this->textOf($config, 'query'),
            'format' => $this->textOf($config, 'format'),
            'extract_depth' => $this->textOf($config, 'extract_depth'),
            'chunks_per_source' => $this->intOf($config, 'chunks_per_source'),
            'include_images' => $this->boolOf($config, 'include_images'),
            'include_favicon' => $this->boolOf($config, 'include_favicon'),
            'timeout' => $timeout,
            'include_usage' => true,
        ]), $timeout + 15);

        return ['results' => array_values((array) ($result['results'] ?? [])), 'failed' => array_values((array) ($result['failed_results'] ?? [])), 'credits' => data_get($result, 'usage.credits'), 'request_id' => $result['request_id'] ?? null];
    }
}
