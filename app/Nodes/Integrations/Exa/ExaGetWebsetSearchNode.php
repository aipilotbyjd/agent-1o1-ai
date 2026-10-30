<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaGetWebsetSearchNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_get_webset_search';
    }

    public function name(): string
    {
        return 'Exa: Get Webset Search';
    }

    public function description(): string
    {
        return 'Reads how a webset\'s search is going.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['webset_id', 'search_id'],
            'properties' => [
                ...$this->credentialFields(),
                'webset_id' => $this->websetField(),
                'search_id' => Field::text('Search id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['search' => $this->exa($run, $config, 'GET', self::WEBSETS.'/websets/'.$this->pathId($config, 'webset_id', 'The webset').'/searches/'.$this->pathId($config, 'search_id', 'The search id'))];
    }
}
