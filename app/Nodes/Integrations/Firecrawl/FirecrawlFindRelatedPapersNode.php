<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlFindRelatedPapersNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_find_related_papers';
    }

    public function name(): string
    {
        return 'Firecrawl: Find Related Papers';
    }

    public function description(): string
    {
        return 'Finds papers like one, those citing it, or those it cites.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['paper_id', 'intent'],
            'properties' => [
                ...$this->credentialFields(),
                'paper_id' => Field::text('Paper id'),
                'intent' => Field::textarea('What you are looking for'),
                'mode' => Field::select('Mode', ['similar', 'citers', 'references'], null, 'similar'),
                'k' => Field::advanced(Field::integer('Max results', null, null, 1, 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'GET', '/search/research/papers/'.rawurlencode($this->requiredString($config, 'paper_id', 'The paper id')).'/similar', query: ['intent' => $this->requiredString($config, 'intent', 'What you are looking for'), 'mode' => $this->textOf($config, 'mode'), 'k' => $this->intOf($config, 'k')], timeout: 60);

        return ['results' => $answer['results'] ?? [], 'pool_size' => $answer['poolSize'] ?? null, 'truncated' => $answer['truncated'] ?? null];
    }
}
