<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlSearchDeveloperNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_search_developer';
    }

    public function name(): string
    {
        return 'Firecrawl: Search Developer Index';
    }

    public function description(): string
    {
        return 'Answers programming questions from issues, merged pull requests, READMEs and curated docs.';
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
                'query' => Field::textarea('Search for'),
                'k' => Field::integer('Max results', null, 10, 1, 100),
                'types' => Field::advanced(Field::list('Kinds', 'doc, issue, pull_request, readme.')),
                'repos' => Field::advanced(Field::list('Only these repositories', null, 'owner/name')),
                'language' => Field::advanced(Field::text('Programming language')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'POST', '/search/developer', $this->withoutEmpty([
            'query' => $this->requiredString($config, 'query', 'What to search for'),
            'k' => $this->intOf($config, 'k'),
            'types' => $this->stringList($config['types'] ?? []),
            'repos' => $this->stringList($config['repos'] ?? []),
            'language' => $this->textOf($config, 'language'),
        ]), timeout: 60);

        return ['results' => $answer['results'] ?? [], 'repos' => $answer['repos'] ?? [], 'sources' => $answer['sources'] ?? []];
    }
}
