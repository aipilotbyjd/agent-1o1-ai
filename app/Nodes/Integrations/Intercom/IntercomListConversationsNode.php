<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomListConversationsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_conversations';
    }

    public function name(): string
    {
        return 'Intercom: List Conversations';
    }

    public function description(): string
    {
        return 'Lists conversations, latest change first.';
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
        $rows = $this->cursorAll($run, $config, '/conversations', 'conversations', [], null, $this->limit($config, 50));

        return ['conversations' => array_map($this->shapeConversation(...), $rows), 'count' => count($rows)];
    }
}
