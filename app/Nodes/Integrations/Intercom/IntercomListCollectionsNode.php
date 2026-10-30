<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomListCollectionsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_collections';
    }

    public function name(): string
    {
        return 'Intercom: List Help Center Collections';
    }

    public function description(): string
    {
        return 'Lists the Help Center\'s collections.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->pagedAll($run, $config, '/help_center/collections', 'data', [], $this->limit($config, 50));

        return ['collections' => $rows, 'count' => count($rows)];
    }
}
