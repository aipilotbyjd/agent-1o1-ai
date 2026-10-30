<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloGetConversationExportNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_conversation_export';
    }

    public function name(): string
    {
        return 'Apollo: Get Conversation Export';
    }

    public function description(): string
    {
        return 'Reads where a finished conversation export can be downloaded.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['export_id'],
            'properties' => [
                ...$this->credentialFields(),
                'export_id' => Field::text('Export id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->apollo($run, $config, 'GET', '/conversations/export/'.$this->recordId($config, 'export_id', 'The export id'));
    }
}
