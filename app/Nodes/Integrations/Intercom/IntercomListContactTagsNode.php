<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomListContactTagsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_contact_tags';
    }

    public function name(): string
    {
        return 'Intercom: List Contact Tags';
    }

    public function description(): string
    {
        return 'Lists the tags on a contact.';
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
        $rows = (array) ($this->intercom($run, $config, 'GET', "/contacts/{$id}/tags")['data'] ?? []);

        return ['tags' => $rows, 'count' => count($rows)];
    }
}
