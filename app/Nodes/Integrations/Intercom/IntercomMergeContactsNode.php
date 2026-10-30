<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomMergeContactsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_merge_contacts';
    }

    public function name(): string
    {
        return 'Intercom: Merge Lead Into User';
    }

    public function description(): string
    {
        return 'Merges a lead into a user. The lead is gone afterwards.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['lead_id', 'user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'lead_id' => Field::text('Lead id'),
                'user_id' => Field::text('User id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeContact($this->intercom($run, $config, 'POST', '/contacts/merge', [], ['from' => $this->safeId($config, 'lead_id', 'The lead'), 'into' => $this->safeId($config, 'user_id', 'The user')]));
    }
}
