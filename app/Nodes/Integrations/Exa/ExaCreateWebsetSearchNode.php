<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExaCreateWebsetSearchNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_create_webset_search';
    }

    public function name(): string
    {
        return 'Exa: Add Webset Search';
    }

    public function description(): string
    {
        return 'Runs another search into a webset, to find more items.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['webset_id', 'query'],
            'properties' => [
                ...$this->credentialFields(),
                'webset_id' => $this->websetField(),
                ...$this->websetSearchFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['search' => $this->exa($run, $config, 'POST', self::WEBSETS.'/websets/'.$this->pathId($config, 'webset_id', 'The webset').'/searches', $this->websetSearchBody($config))];
    }
}
