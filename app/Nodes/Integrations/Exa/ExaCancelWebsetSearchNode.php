<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaCancelWebsetSearchNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_cancel_webset_search';
    }

    public function name(): string
    {
        return 'Exa: Cancel Webset Search';
    }

    public function description(): string
    {
        return 'Cancels a webset\'s running search.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        return ['search' => $this->exa($run, $config, 'POST', self::WEBSETS.'/websets/'.$this->pathId($config, 'webset_id', 'The webset').'/searches/'.$this->pathId($config, 'search_id', 'The search id').'/cancel', [])];
    }
}
