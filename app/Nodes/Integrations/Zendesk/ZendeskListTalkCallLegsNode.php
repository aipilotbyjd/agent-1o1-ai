<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskListTalkCallLegsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_list_talk_call_legs';
    }

    public function name(): string
    {
        return 'Zendesk: List Talk Call Legs';
    }

    public function description(): string
    {
        return 'Lists the legs of Zendesk Talk calls since a time, optionally of one call.';
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
                'call_id' => Field::advanced(Field::text('Call id')),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $legs = $this->talkExport($run, $config, 'legs');
        $call = $this->optionalId($config, 'call_id', 'The call');
        if ($call === null) {
            return $legs;
        }
        $rows = array_values(array_filter($legs['legs'], fn (array $leg): bool => (int) ($leg['call_id'] ?? 0) === $call));

        return ['legs' => $rows, 'count' => count($rows)];
    }
}
