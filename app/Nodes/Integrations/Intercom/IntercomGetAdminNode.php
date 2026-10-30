<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomGetAdminNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_get_admin';
    }

    public function name(): string
    {
        return 'Intercom: Get Admin';
    }

    public function description(): string
    {
        return 'Reads a teammate by id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['admin_id'],
            'properties' => [
                ...$this->credentialFields(),
                'admin_id' => Field::dynamic('Teammate', 'intercom.admins'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'admin_id', 'The teammate');

        return $this->findOne($run, $config, "/admins/{$id}", $id);
    }
}
