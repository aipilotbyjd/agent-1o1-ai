<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExaListWebhooksNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_list_webhooks';
    }

    public function name(): string
    {
        return 'Exa: List Websets Webhooks';
    }

    public function description(): string
    {
        return 'Lists the team\'s Websets webhooks.';
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
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeList($this->exa($run, $config, 'GET', self::WEBSETS.'/webhooks', query: $this->pageQuery($config)));
    }
}
