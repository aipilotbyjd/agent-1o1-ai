<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExaCancelWebsetNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_cancel_webset';
    }

    public function name(): string
    {
        return 'Exa: Cancel Webset';
    }

    public function description(): string
    {
        return 'Cancels everything a webset is doing.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        return ['webset' => $this->exa($run, $config, 'POST', self::WEBSETS.'/websets/'.$this->pathId($config, 'webset_id', 'The webset').'/cancel', [])];
    }
}
