<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomFindOrCreateLeadNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_find_or_create_lead';
    }

    public function name(): string
    {
        return 'Intercom: Find or Create Lead';
    }

    public function description(): string
    {
        return 'Finds the lead with this email, or creates one.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'name' => Field::text('Name', 'Used when they are new.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $email = $this->email($config);
        $found = (array) ($this->intercom($run, $config, 'POST', '/contacts/search', [], ['query' => $this->allOf([['field' => 'role', 'operator' => '=', 'value' => 'lead'], ['field' => 'email', 'operator' => '=', 'value' => $email]])])['data'] ?? []);
        if ($found !== []) {
            return ['created' => false, ...$this->shapeContact((array) $found[0])];
        }

        return ['created' => true, ...$this->shapeContact($this->intercom($run, $config, 'POST', '/contacts', [], $this->present(['role' => 'lead', 'email' => $email, 'name' => $this->text($config, 'name')])))];
    }
}
