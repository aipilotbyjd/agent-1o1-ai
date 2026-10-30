<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetMacroNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_macro';
    }

    public function name(): string
    {
        return 'Zendesk: Get Macro';
    }

    public function description(): string
    {
        return 'Reads one macro\'s actions.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['macro_id'],
            'properties' => [
                ...$this->credentialFields(),
                'macro_id' => Field::dynamic('Macro', 'zendesk.macros'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'macro_id', 'The macro');

        return $this->findOne($run, $config, "/macros/{$id}", 'macro', $id);
    }
}
