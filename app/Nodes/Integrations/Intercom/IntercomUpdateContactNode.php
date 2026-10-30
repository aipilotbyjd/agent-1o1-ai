<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomUpdateContactNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_update_contact';
    }

    public function name(): string
    {
        return 'Intercom: Update Contact';
    }

    public function description(): string
    {
        return 'Changes a contact. Only what is filled in changes.';
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
                ...$this->contactFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'contact_id', 'The contact');
        $body = $this->contactBody($config);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeContact($this->intercom($run, $config, 'PUT', "/contacts/{$id}", [], $body));
    }
}
