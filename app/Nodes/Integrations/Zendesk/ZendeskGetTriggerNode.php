<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetTriggerNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_trigger';
    }

    public function name(): string
    {
        return 'Zendesk: Get Business Rule Trigger';
    }

    public function description(): string
    {
        return 'Reads one trigger\'s conditions and actions.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['trigger_id'],
            'properties' => [
                ...$this->credentialFields(),
                'trigger_id' => Field::text('Trigger id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'trigger_id', 'The trigger');

        return $this->findOne($run, $config, "/triggers/{$id}", 'trigger', $id);
    }
}
