<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class IntercomCreateContactNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_create_contact';
    }

    public function name(): string
    {
        return 'Intercom: Create Contact';
    }

    public function description(): string
    {
        return 'Creates a user or a lead. Needs an email, external id or role.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->contactFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->contactBody($config);
        if ($body === []) {
            throw new \InvalidArgumentException('Give at least an email, an external id or a role.');
        }

        return $this->shapeContact($this->intercom($run, $config, 'POST', '/contacts', [], $body));
    }
}
