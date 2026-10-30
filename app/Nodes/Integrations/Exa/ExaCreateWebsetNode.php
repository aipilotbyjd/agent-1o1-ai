<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaCreateWebsetNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_create_webset';
    }

    public function name(): string
    {
        return 'Exa: Create Webset';
    }

    public function description(): string
    {
        return 'Starts building a verified list of companies, people or pages from a description, and returns its id at once. Follow it with the New Webset Item trigger.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'title' => Field::text('Title'),
                ...$this->websetSearchFields(),
                'enrichments' => Field::advanced(Field::json('Enrichments', 'A list of {"description": "...", "format": "text"} to find out for each item.', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['webset' => $this->exa($run, $config, 'POST', self::WEBSETS.'/websets', $this->withoutEmpty(['title' => $this->textOf($config, 'title'), 'search' => $this->websetSearchBody($config), 'enrichments' => $this->decoded($config['enrichments'] ?? null, 'The enrichments')]))];
    }
}
