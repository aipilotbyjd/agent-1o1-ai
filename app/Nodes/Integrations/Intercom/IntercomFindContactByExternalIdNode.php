<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomFindContactByExternalIdNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_find_contact_by_external_id';
    }

    public function name(): string
    {
        return 'Intercom: Find Contact By External Id';
    }

    public function description(): string
    {
        return 'Reads a contact by your own id for them, or says they were not found.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['external_id'],
            'properties' => [
                ...$this->credentialFields(),
                'external_id' => Field::text('External id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'external_id', 'The external id');

        return $this->findOne($run, $config, "/contacts/find_by_external_id/{$id}", $id, $this->shapeContact(...));
    }
}
