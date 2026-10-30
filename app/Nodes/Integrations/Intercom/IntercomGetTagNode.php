<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomGetTagNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_get_tag';
    }

    public function name(): string
    {
        return 'Intercom: Get Tag';
    }

    public function description(): string
    {
        return 'Reads a tag by id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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

        return $this->findOne($run, $config, "/tags/{$id}", $id);
    }
}
