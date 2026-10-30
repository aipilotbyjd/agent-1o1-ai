<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaDeleteEnrichmentNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_delete_enrichment';
    }

    public function name(): string
    {
        return 'Exa: Delete Webset Enrichment';
    }

    public function description(): string
    {
        return 'Removes an enrichment from a webset.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->exa($run, $config, 'DELETE', self::WEBSETS.'/websets/'.$this->pathId($config, 'webset_id', 'The webset').'/enrichments/'.$this->pathId($config, 'enrichment_id', 'The enrichment id'));

        return ['deleted' => true];
    }
}
