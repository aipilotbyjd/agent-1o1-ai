<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomBlockContactNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_block_contact';
    }

    public function name(): string
    {
        return 'Intercom: Block Contact';
    }

    public function description(): string
    {
        return 'Blocks a contact from messaging you.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'contact_id', 'The contact');
        $this->intercom($run, $config, 'POST', "/contacts/{$id}/block");

        return ['blocked' => true, 'id' => $id];
    }
}
