<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetAutomationNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_automation';
    }

    public function name(): string
    {
        return 'Zendesk: Get Automation';
    }

    public function description(): string
    {
        return 'Reads one automation\'s conditions and actions.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['automation_id'],
            'properties' => [
                ...$this->credentialFields(),
                'automation_id' => Field::text('Automation id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'automation_id', 'The automation');

        return $this->findOne($run, $config, "/automations/{$id}", 'automation', $id);
    }
}
