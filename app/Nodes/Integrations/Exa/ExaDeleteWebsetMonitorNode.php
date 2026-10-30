<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaDeleteWebsetMonitorNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_delete_webset_monitor';
    }

    public function name(): string
    {
        return 'Exa: Delete Webset Monitor';
    }

    public function description(): string
    {
        return 'Deletes a webset monitor for good.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_id' => Field::text('Monitor id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->exa($run, $config, 'DELETE', self::WEBSETS.'/monitors/'.$this->pathId($config, 'monitor_id', 'The monitor id'));

        return ['deleted' => true];
    }
}
