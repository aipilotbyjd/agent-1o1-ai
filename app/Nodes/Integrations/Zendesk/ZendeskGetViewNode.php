<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetViewNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_view';
    }

    public function name(): string
    {
        return 'Zendesk: Get View';
    }

    public function description(): string
    {
        return 'Reads one view\'s conditions and columns.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['view_id'],
            'properties' => [
                ...$this->credentialFields(),
                'view_id' => Field::dynamic('View', 'zendesk.views'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'view_id', 'The view');

        return $this->findOne($run, $config, "/views/{$id}", 'view', $id);
    }
}
