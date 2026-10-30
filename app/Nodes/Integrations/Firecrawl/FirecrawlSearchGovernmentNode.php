<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlSearchGovernmentNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_search_government';
    }

    public function name(): string
    {
        return 'Firecrawl: Search Government Index';
    }

    public function description(): string
    {
        return 'Searches statutes, regulations, codes, court opinions and other government publications.';
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
                'jurisdiction' => Field::advanced(Field::text('Jurisdiction')),
                'court' => Field::advanced(Field::text('Court')),
                'court_type' => Field::advanced(Field::text('Court type', null, 'federal-supreme')),
                'cites' => Field::advanced(Field::text('Cites')),
                'domain' => Field::advanced(Field::text('Site')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'POST', '/search/gov', $this->withoutEmpty([
            'query' => $this->requiredString($config, 'query', 'What to search for'),
            'k' => $this->intOf($config, 'k'),
            'jurisdiction' => $this->textOf($config, 'jurisdiction'),
            'court' => $this->textOf($config, 'court'),
            'courtType' => $this->textOf($config, 'court_type'),
            'cites' => $this->textOf($config, 'cites'),
            'domain' => $this->textOf($config, 'domain'),
        ]), timeout: 60);

        return ['results' => $answer['data'] ?? []];
    }
}
