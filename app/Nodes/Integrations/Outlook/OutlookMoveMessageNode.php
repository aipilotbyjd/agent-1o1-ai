<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class OutlookMoveMessageNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_move_message';
    }

    public function name(): string
    {
        return 'Outlook: Move Message';
    }

    public function description(): string
    {
        return 'Moves a message to another folder, e.g. archive.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id', 'folder'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'string'],
                'message_id' => ['type' => 'string'],
                'folder' => ['type' => 'string', 'description' => 'A folder id or a well-known name: inbox, archive, junkemail, deleteditems.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, '/me/messages/'.rawurlencode((string) $config['message_id']).'/move', $config, [
            'destinationId' => $config['folder'],
        ]);
    }
}
