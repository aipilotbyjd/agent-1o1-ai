<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaGetEnrichmentNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_get_enrichment';
    }

    public function name(): string
    {
        return 'Exa: Get Webset Enrichment';
    }

    public function description(): string
    {
        return 'Reads one of a webset\'s enrichments and how it is going.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['enrichment' => $this->exa($run, $config, 'GET', self::WEBSETS.'/websets/'.$this->pathId($config, 'webset_id', 'The webset').'/enrichments/'.$this->pathId($config, 'enrichment_id', 'The enrichment id'))];
    }
}
