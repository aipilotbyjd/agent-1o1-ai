<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskCreateContactNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_contact';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Contact';
    }

    public function description(): string
    {
        return 'Creates a contact. Needs a name and one of email, phone, mobile, Twitter or external id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->contactFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->requiredString($config, 'name', 'The name');

        return $this->shapeContact($this->freshdesk($run, $config, 'POST', '/contacts', [], $this->contactBody($config)));
    }
}
