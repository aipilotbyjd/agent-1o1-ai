<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskRestoreContactNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_restore_contact';
    }

    public function name(): string
    {
        return 'Freshdesk: Restore Contact';
    }

    public function description(): string
    {
        return 'Brings back a deleted contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::text('Contact id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'contact_id', 'The contact');
        $this->freshdesk($run, $config, 'PUT', "/contacts/{$id}/restore");

        return ['restored' => true, 'id' => (int) $id];
    }
}
