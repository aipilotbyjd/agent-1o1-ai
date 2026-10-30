<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomTagContactNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_tag_contact';
    }

    public function name(): string
    {
        return 'Intercom: Add Tag To Contact';
    }

    public function description(): string
    {
        return 'Puts a tag on a contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id', 'tag_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::text('Contact id'),
                'tag_id' => Field::dynamic('Tag', 'intercom.tags', null, 'VIP'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'contact_id', 'The contact');

        return $this->intercom($run, $config, 'POST', "/contacts/{$id}/tags", [], ['id' => $this->safeId($config, 'tag_id', 'The tag')]);
    }
}
