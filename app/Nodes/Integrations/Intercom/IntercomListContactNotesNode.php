<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomListContactNotesNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_contact_notes';
    }

    public function name(): string
    {
        return 'Intercom: List Contact Notes';
    }

    public function description(): string
    {
        return 'Lists the notes on a contact.';
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
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'contact_id', 'The contact');
        $rows = $this->pagedAll($run, $config, "/contacts/{$id}/notes", 'data', [], $this->limit($config, 50));

        return ['notes' => $rows, 'count' => count($rows)];
    }
}
