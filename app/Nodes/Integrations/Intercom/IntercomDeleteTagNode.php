<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomDeleteTagNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_delete_tag';
    }

    public function name(): string
    {
        return 'Intercom: Delete Tag';
    }

    public function description(): string
    {
        return 'Deletes a tag. It must not be in use by a message or a rule.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['tag_id'],
            'properties' => [
                ...$this->credentialFields(),
                'tag_id' => Field::dynamic('Tag', 'intercom.tags'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'tag_id', 'The tag');
        $this->intercom($run, $config, 'DELETE', "/tags/{$id}");

        return ['deleted' => true, 'id' => $id];
    }
}
