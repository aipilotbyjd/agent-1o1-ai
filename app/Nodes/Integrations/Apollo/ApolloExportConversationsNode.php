<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloExportConversationsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_export_conversations';
    }

    public function name(): string
    {
        return 'Apollo: Export Conversations';
    }

    public function description(): string
    {
        return 'Starts an export of the conversations in a time range, and Apollo emails the file to the address given.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['start_time', 'end_time', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'start_time' => Field::datetime('From'),
                'end_time' => Field::datetime('To'),
                'email' => Field::text('Email the export to'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->apollo($run, $config, 'POST', '/conversations/export', [], ['start_time' => $this->requiredString($config, 'start_time', 'The start'), 'end_time' => $this->requiredString($config, 'end_time', 'The end'), 'email' => $this->requiredString($config, 'email', 'The email')]);
    }
}
