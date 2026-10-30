<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaCancelEnrichmentNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_cancel_enrichment';
    }

    public function name(): string
    {
        return 'Exa: Cancel Webset Enrichment';
    }

    public function description(): string
    {
        return 'Cancels a webset\'s running enrichment.';
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
        return ['enrichment' => $this->exa($run, $config, 'POST', self::WEBSETS.'/websets/'.$this->pathId($config, 'webset_id', 'The webset').'/enrichments/'.$this->pathId($config, 'enrichment_id', 'The enrichment id').'/cancel', [])];
    }
}
