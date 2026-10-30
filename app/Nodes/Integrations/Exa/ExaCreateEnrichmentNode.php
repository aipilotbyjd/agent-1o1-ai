<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaCreateEnrichmentNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_create_enrichment';
    }

    public function name(): string
    {
        return 'Exa: Add Webset Enrichment';
    }

    public function description(): string
    {
        return 'Finds out something more about each item of a webset.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['webset_id', 'description'],
            'properties' => [
                ...$this->credentialFields(),
                'webset_id' => $this->websetField(),
                'description' => Field::textarea('What to find out', null, 'The CEO\'s name'),
                'format' => Field::select('Format', ['text', 'date', 'number', 'options', 'email', 'phone', 'url'], null, 'text'),
                'options' => Field::advanced(Field::json('Options', 'For the options format: a list of {"label": "..."}.', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['enrichment' => $this->exa($run, $config, 'POST', self::WEBSETS.'/websets/'.$this->pathId($config, 'webset_id', 'The webset').'/enrichments', $this->withoutEmpty(['description' => $this->requiredString($config, 'description', 'What to find out'), 'format' => $this->textOf($config, 'format'), 'options' => $this->decoded($config['options'] ?? null, 'The options')]))];
    }
}
