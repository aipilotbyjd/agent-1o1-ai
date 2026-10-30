<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomSetAdminAwayNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_set_admin_away';
    }

    public function name(): string
    {
        return 'Intercom: Set Admin As Away';
    }

    public function description(): string
    {
        return 'Sets a teammate to away or back, and whether new replies go to others.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['admin_id'],
            'properties' => [
                ...$this->credentialFields(),
                'admin_id' => Field::dynamic('Teammate', 'intercom.admins'),
                'away' => Field::boolean('Away'),
                'reassign' => Field::advanced(Field::boolean('Reassign new replies')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'admin_id', 'The teammate');

        return $this->intercom($run, $config, 'PUT', "/admins/{$id}/away", [], ['away_mode_enabled' => filter_var($config['away'] ?? false, FILTER_VALIDATE_BOOLEAN), 'away_mode_reassign' => filter_var($config['reassign'] ?? false, FILTER_VALIDATE_BOOLEAN)]);
    }
}
