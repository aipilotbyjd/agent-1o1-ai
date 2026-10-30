<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyUpdateStoreNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_update_store';
    }

    public function name(): string
    {
        return 'Apify: Rename Key-Value Store';
    }

    public function description(): string
    {
        return 'Renames a key-value store, or changes who can read it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['store_id'],
            'properties' => [
                ...$this->credentialFields(),
                'store_id' => $this->storeField(),
                'name' => Field::text('Name'),
                'general_access' => Field::advanced(Field::select('Who can read it', ['FOLLOW_USER_SETTING', 'RESTRICTED', 'ANYONE_WITH_ID_CAN_READ', 'ANYONE_WITH_NAME_CAN_READ'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['store' => $this->apify($run, $config, 'PUT', '/key-value-stores/'.$this->pathId($config, 'store_id', 'The store'), $this->withoutEmpty(['name' => $this->textOf($config, 'name'), 'generalAccess' => $this->textOf($config, 'general_access')]))];
    }
}
