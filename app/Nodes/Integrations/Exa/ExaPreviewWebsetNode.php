<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaPreviewWebsetNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_preview_webset';
    }

    public function name(): string
    {
        return 'Exa: Preview Webset';
    }

    public function description(): string
    {
        return 'Shows how a description would be read as a webset, before making one.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::textarea('Find'),
                'count' => Field::integer('How many', null, 10, 1),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['preview' => $this->exa($run, $config, 'POST', self::WEBSETS.'/websets/preview', ['search' => $this->withoutEmpty(['query' => $this->requiredString($config, 'query', 'What to find'), 'count' => $this->intOf($config, 'count')])], timeout: 60)];
    }
}
