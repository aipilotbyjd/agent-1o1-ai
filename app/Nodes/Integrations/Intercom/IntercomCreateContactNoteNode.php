<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomCreateContactNoteNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_create_contact_note';
    }

    public function name(): string
    {
        return 'Intercom: Create Contact Note';
    }

    public function description(): string
    {
        return 'Adds a note to a contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::text('Contact id'),
                'body' => Field::textarea('Note'),
                'admin_id' => Field::advanced(Field::dynamic('Author', 'intercom.admins')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'contact_id', 'The contact');

        return $this->intercom($run, $config, 'POST', "/contacts/{$id}/notes", [], $this->present(['body' => $this->requiredString($config, 'body', 'The note'), 'admin_id' => $this->text($config, 'admin_id')]));
    }
}
