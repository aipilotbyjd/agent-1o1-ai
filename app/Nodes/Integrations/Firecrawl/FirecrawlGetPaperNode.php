<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlGetPaperNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_paper';
    }

    public function name(): string
    {
        return 'Firecrawl: Read Research Paper';
    }

    public function description(): string
    {
        return 'Reads a paper, or the passages in it about a question.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['paper_id'],
            'properties' => [
                ...$this->credentialFields(),
                'paper_id' => Field::text('Paper id', 'The id Search Research Papers returned.'),
                'query' => Field::text('About', 'Returns the passages about this.'),
                'k' => Field::advanced(Field::integer('Max passages', null, null, 1, 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['paper' => $this->firecrawl($run, $config, 'GET', '/search/research/papers/'.rawurlencode($this->requiredString($config, 'paper_id', 'The paper id')), query: ['query' => $this->textOf($config, 'query'), 'k' => $this->intOf($config, 'k')], timeout: 60)];
    }
}
