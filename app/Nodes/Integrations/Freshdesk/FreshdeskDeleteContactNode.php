<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteContactNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_contact';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Contact';
    }

    public function description(): string
    {
        return 'Moves a contact to deleted contacts. Restore Contact brings them back.';
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
        $id = $this->numericId($config, 'contact_id', 'The contact');

        return $this->destroy($run, $config, "/contacts/{$id}", $id);
    }
}
