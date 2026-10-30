<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaDeleteWebsetItemNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_delete_webset_item';
    }

    public function name(): string
    {
        return 'Exa: Delete Webset Item';
    }

    public function description(): string
    {
        return 'Removes an item from a webset.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['webset_id', 'item_id'],
            'properties' => [
                ...$this->credentialFields(),
                'webset_id' => $this->websetField(),
                'item_id' => Field::text('Item id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->exa($run, $config, 'DELETE', self::WEBSETS.'/websets/'.$this->pathId($config, 'webset_id', 'The webset').'/items/'.$this->pathId($config, 'item_id', 'The item id'));

        return ['deleted' => true];
    }
}
