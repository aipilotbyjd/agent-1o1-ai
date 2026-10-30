<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetEmailConfigNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_email_config';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Email Config';
    }

    public function description(): string
    {
        return 'Reads one sending address.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email_config_id'],
            'properties' => [
                ...$this->credentialFields(),
                'email_config_id' => Field::dynamic('Address', 'freshdesk.email_configs'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'email_config_id', 'The address');

        return $this->findOne($run, $config, "/email_configs/{$id}", $id);
    }
}
