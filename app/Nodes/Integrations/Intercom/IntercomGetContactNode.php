<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomGetContactNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_get_contact';
    }

    public function name(): string
    {
        return 'Intercom: Get Contact';
    }

    public function description(): string
    {
        return 'Reads a contact by id, or says they were not found.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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

        return $this->findOne($run, $config, "/contacts/{$id}", $id, $this->shapeContact(...));
    }
}
