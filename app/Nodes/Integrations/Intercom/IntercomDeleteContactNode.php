<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomDeleteContactNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_delete_contact';
    }

    public function name(): string
    {
        return 'Intercom: Delete Contact';
    }

    public function description(): string
    {
        return 'Deletes a contact for good.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'contact_id', 'The contact');
        $this->intercom($run, $config, 'DELETE', "/contacts/{$id}");

        return ['deleted' => true, 'id' => $id];
    }
}
