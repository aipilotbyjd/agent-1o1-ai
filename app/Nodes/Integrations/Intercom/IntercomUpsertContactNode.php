<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class IntercomUpsertContactNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_upsert_contact';
    }

    public function name(): string
    {
        return 'Intercom: Create or Update Contact';
    }

    public function description(): string
    {
        return 'Finds the contact with this email or external id and changes them, or creates them.';
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
        $external = $this->text($config, 'external_id');
        $email = $body['email'] ?? null;
        if ($external === null && $email === null) {
            throw new \InvalidArgumentException('Give an email or an external id to find the contact by.');
        }
        $found = (array) ($this->intercom($run, $config, 'POST', '/contacts/search', [], ['query' => $external !== null ? ['field' => 'external_id', 'operator' => '=', 'value' => $external] : ['field' => 'email', 'operator' => '=', 'value' => $email]])['data'] ?? []);
        if ($found !== []) {
            return ['created' => false, ...$this->shapeContact($this->intercom($run, $config, 'PUT', '/contacts/'.rawurlencode((string) ($found[0]['id'] ?? '')), [], $body))];
        }

        return ['created' => true, ...$this->shapeContact($this->intercom($run, $config, 'POST', '/contacts', [], $body))];
    }
}
