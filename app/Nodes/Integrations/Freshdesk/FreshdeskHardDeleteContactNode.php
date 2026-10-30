<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskHardDeleteContactNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_hard_delete_contact';
    }

    public function name(): string
    {
        return 'Freshdesk: Hard Delete Contact';
    }

    public function description(): string
    {
        return 'Deletes a contact for good. This cannot be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::text('Contact id'),
                'force' => Field::advanced(Field::boolean('Even if not soft deleted first')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'contact_id', 'The contact');
        $this->freshdesk($run, $config, 'DELETE', "/contacts/{$id}/hard_delete", ['force' => filter_var($config['force'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'true' : null]);

        return ['deleted' => true, 'id' => $id];
    }
}
