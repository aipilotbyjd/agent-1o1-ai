<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyUpdateDatasetNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_update_dataset';
    }

    public function name(): string
    {
        return 'Apify: Rename Dataset';
    }

    public function description(): string
    {
        return 'Renames a dataset, or changes who can read it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['dataset_id'],
            'properties' => [
                ...$this->credentialFields(),
                'dataset_id' => $this->datasetField(),
                'name' => Field::text('Name'),
                'general_access' => Field::advanced(Field::select('Who can read it', ['FOLLOW_USER_SETTING', 'RESTRICTED', 'ANYONE_WITH_ID_CAN_READ', 'ANYONE_WITH_NAME_CAN_READ'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['dataset' => $this->apify($run, $config, 'PUT', '/datasets/'.$this->pathId($config, 'dataset_id', 'The dataset'), $this->withoutEmpty(['name' => $this->textOf($config, 'name'), 'generalAccess' => $this->textOf($config, 'general_access')]))];
    }
}
