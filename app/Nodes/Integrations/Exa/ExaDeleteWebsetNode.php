<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExaDeleteWebsetNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_delete_webset';
    }

    public function name(): string
    {
        return 'Exa: Delete Webset';
    }

    public function description(): string
    {
        return 'Deletes a webset and its items.';
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
        $this->exa($run, $config, 'DELETE', self::WEBSETS.'/websets/'.$this->pathId($config, 'webset_id', 'The webset'));

        return ['deleted' => true];
    }
}
