<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomGetSegmentNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_get_segment';
    }

    public function name(): string
    {
        return 'Intercom: Get Segment';
    }

    public function description(): string
    {
        return 'Reads a segment by id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['segment_id'],
            'properties' => [
                ...$this->credentialFields(),
                'segment_id' => Field::text('Segment id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'segment_id', 'The segment');

        return $this->findOne($run, $config, "/segments/{$id}", $id);
    }
}
