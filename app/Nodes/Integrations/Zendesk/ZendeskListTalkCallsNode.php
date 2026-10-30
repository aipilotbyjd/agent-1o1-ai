<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskListTalkCallsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_list_talk_calls';
    }

    public function name(): string
    {
        return 'Zendesk: List Talk Calls';
    }

    public function description(): string
    {
        return 'Lists Zendesk Talk calls since a time.';
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
                'since' => Field::datetime('Since', 'Empty is the last day.'),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->talkExport($run, $config, 'calls');
    }
}
