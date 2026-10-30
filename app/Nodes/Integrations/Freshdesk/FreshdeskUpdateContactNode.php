<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateContactNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_contact';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Contact';
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
        $id = $this->numericId($config, 'contact_id', 'The contact');
        $body = $this->contactBody($config);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeContact($this->freshdesk($run, $config, 'PUT', "/contacts/{$id}", [], $body));
    }
}
