<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomSearchConversationsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_search_conversations';
    }

    public function name(): string
    {
        return 'Intercom: Search Conversations';
    }

    public function description(): string
    {
        return 'Finds conversations by a field, such as state, tag_ids, source.body or contact_ids.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['field', 'value'],
            'properties' => [
                ...$this->credentialFields(),
                'field' => Field::text('Field', 'e.g. state, open, tag_ids, contact_ids, source.body, admin_assignee_id.', 'state'),
                'operator' => Field::advanced(Field::select('Match', ['=' => 'Is', '!=' => 'Is not', '~' => 'Contains', '!~' => 'Does not contain', '^' => 'Starts with', '$' => 'Ends with', '>' => 'Greater than', '<' => 'Less than'], null, '=')),
                'value' => Field::text('Value', null, 'open'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->cursorAll($run, $config, '/conversations/search', 'conversations', [], ['field' => $this->requiredString($config, 'field', 'The field'), 'operator' => trim((string) ($config['operator'] ?? '=')), 'value' => $this->requiredString($config, 'value', 'The value')], $this->limit($config, 50));

        return ['conversations' => array_map($this->shapeConversation(...), $rows), 'count' => count($rows)];
    }
}
