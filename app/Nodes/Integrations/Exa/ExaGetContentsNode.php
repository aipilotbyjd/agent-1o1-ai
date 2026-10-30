<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ExaGetContentsNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_get_contents';
    }

    public function name(): string
    {
        return 'Exa: Get Contents';
    }

    public function description(): string
    {
        return 'Reads clean text, highlights, summaries and details for a list of pages, by web address or Exa result id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'urls' => Field::list('Pages', null, 'https://example.com'),
                'ids' => Field::advanced(Field::list('Or Exa result ids')),
                ...$this->contentFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $urls = array_map($this->checkedUrl(...), $this->stringList($config['urls'] ?? []));
        $ids = $this->stringList($config['ids'] ?? []);

        if ($urls === [] && $ids === []) {
            throw new InvalidArgumentException('Give at least one page to read.');
        }

        return $this->shapeResults($this->exa($run, $config, 'POST', '/contents', $this->withoutEmpty(['urls' => $urls, 'ids' => $ids, ...$this->contentOptions($config)]), timeout: 75));
    }
}
