<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class IntercomListSegmentsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_segments';
    }

    public function name(): string
    {
        return 'Intercom: List Segments';
    }

    public function description(): string
    {
        return 'Lists the workspace\'s segments.';
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
        $rows = (array) ($this->intercom($run, $config, 'GET', '/segments')['segments'] ?? []);

        return ['segments' => $rows, 'count' => count($rows)];
    }
}
