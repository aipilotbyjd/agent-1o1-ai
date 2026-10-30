<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class IntercomListTagsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_tags';
    }

    public function name(): string
    {
        return 'Intercom: List Tags';
    }

    public function description(): string
    {
        return 'Lists the workspace\'s tags.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = (array) ($this->intercom($run, $config, 'GET', '/tags')['data'] ?? []);

        return ['tags' => $rows, 'count' => count($rows)];
    }
}
