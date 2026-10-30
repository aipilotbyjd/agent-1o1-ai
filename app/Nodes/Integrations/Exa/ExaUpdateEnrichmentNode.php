<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaUpdateEnrichmentNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_update_enrichment';
    }

    public function name(): string
    {
        return 'Exa: Update Webset Enrichment';
    }

    public function description(): string
    {
        return 'Changes one of a webset\'s enrichments. Empty settings stay as they are.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['webset_id', 'enrichment_id'],
            'properties' => [
                ...$this->credentialFields(),
                'webset_id' => $this->websetField(),
                'enrichment_id' => Field::text('Enrichment id'),
                'description' => Field::textarea('What to find out'),
                'format' => Field::select('Format', ['text', 'date', 'number', 'options', 'email', 'phone', 'url']),
                'options' => Field::advanced(Field::json('Options', null, 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['enrichment' => $this->exa($run, $config, 'PATCH', self::WEBSETS.'/websets/'.$this->pathId($config, 'webset_id', 'The webset').'/enrichments/'.$this->pathId($config, 'enrichment_id', 'The enrichment id'), $this->withoutEmpty(['description' => $this->textOf($config, 'description'), 'format' => $this->textOf($config, 'format'), 'options' => $this->decoded($config['options'] ?? null, 'The options')]))];
    }
}
