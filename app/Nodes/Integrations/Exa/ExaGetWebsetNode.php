<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExaGetWebsetNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_get_webset';
    }

    public function name(): string
    {
        return 'Exa: Get Webset';
    }

    public function description(): string
    {
        return 'Reads a webset with its status, searches and enrichments.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['webset_id'],
            'properties' => [
                ...$this->credentialFields(),
                'webset_id' => $this->websetField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['webset' => $this->exa($run, $config, 'GET', self::WEBSETS.'/websets/'.$this->pathId($config, 'webset_id', 'The webset'))];
    }
}
