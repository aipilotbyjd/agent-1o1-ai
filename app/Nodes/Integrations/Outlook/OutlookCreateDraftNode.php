<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class OutlookCreateDraftNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_create_draft';
    }

    public function name(): string
    {
        return 'Outlook: Create Draft';
    }

    public function description(): string
    {
        return 'Creates a draft email in the Drafts folder.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['to', 'subject', 'body'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'string'],
                'to' => ['type' => 'string'],
                'cc' => ['type' => 'string'],
                'subject' => ['type' => 'string'],
                'body' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, '/me/messages', $config, [
            'subject' => $config['subject'],
            'body' => ['contentType' => 'Text', 'content' => $config['body']],
            'toRecipients' => $this->recipients($config['to']),
            'ccRecipients' => $this->recipients($config['cc'] ?? null),
        ]);
    }
}
