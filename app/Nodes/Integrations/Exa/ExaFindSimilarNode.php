<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaFindSimilarNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_find_similar';
    }

    public function name(): string
    {
        return 'Exa: Find Similar';
    }

    public function description(): string
    {
        return 'Finds pages like a given page, and can read each one.';
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
                'url' => Field::text('Page', null, 'https://example.com'),
                'exclude_source_domain' => Field::boolean('Leave out the page\'s own site'),
                ...$this->filterFields(),
                ...$this->contentFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $contents = $this->contentOptions($config);

        return $this->shapeResults($this->exa($run, $config, 'POST', '/findSimilar', $this->withoutEmpty([
            'url' => $this->webUrl($config),
            'excludeSourceDomain' => $this->boolOf($config, 'exclude_source_domain'),
            ...$this->filterOptions($config),
            'contents' => $contents,
        ]), timeout: 75));
    }
}
