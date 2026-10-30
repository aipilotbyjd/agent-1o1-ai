<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class FirecrawlStartExtractNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_start_extract';
    }

    public function name(): string
    {
        return 'Firecrawl: Start Extract';
    }

    public function description(): string
    {
        return 'Starts pulling structured data from pages, described in words or a JSON schema, and returns the job id at once. Read it with Get Extract Status.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['urls'],
            'properties' => [
                ...$this->credentialFields(),
                'urls' => Field::list('Pages', 'A trailing /* takes the whole site.', 'https://example.com/*'),
                'prompt' => Field::textarea('What to extract'),
                'schema' => Field::json('Shape', 'A JSON schema for the result.'),
                'enable_web_search' => Field::advanced(Field::boolean('Also search the web')),
                'include_subdomains' => Field::advanced(Field::boolean('Include subdomains', null, true)),
                'show_sources' => Field::advanced(Field::boolean('Show sources')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $prompt = $this->textOf($config, 'prompt');
        $schema = $this->decoded($config['schema'] ?? null, 'The shape');

        if ($prompt === null && $schema === null) {
            throw new InvalidArgumentException('Say what to extract, in words or as a schema.');
        }

        $answer = $this->firecrawl($run, $config, 'POST', '/extract', $this->withoutEmpty([
            'urls' => $this->webUrls($config),
            'prompt' => $prompt,
            'schema' => $schema,
            'enableWebSearch' => $this->boolOf($config, 'enable_web_search'),
            'includeSubdomains' => $this->boolOf($config, 'include_subdomains'),
            'showSources' => $this->boolOf($config, 'show_sources'),
        ]));

        return ['id' => $answer['id'] ?? null, 'invalid_urls' => $answer['invalidURLs'] ?? []];
    }
}
