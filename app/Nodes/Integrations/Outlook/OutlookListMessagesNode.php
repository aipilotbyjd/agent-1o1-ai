<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class OutlookListMessagesNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_list_messages';
    }

    public function name(): string
    {
        return 'Outlook: List Messages';
    }

    public function description(): string
    {
        return 'Lists messages in a mail folder (the inbox by default), newest first.';
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
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'string'],
                'folder' => ['type' => 'string'],
                'search' => ['type' => 'string'],
                'received_after' => ['type' => 'string'],
                'max_results' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = [
            '$top' => min((int) ($config['max_results'] ?? 10), 50),
            '$select' => 'id,conversationId,subject,from,toRecipients,ccRecipients,receivedDateTime,bodyPreview,webLink,isRead,categories',
        ];

        // Graph doesn't allow $search together with $filter or $orderby.
        if (filled($config['search'] ?? null)) {
            $query['$search'] = '"'.str_replace('"', '', (string) $config['search']).'"';
        } else {
            $query['$orderby'] = 'receivedDateTime desc';

            if (filled($config['received_after'] ?? null)) {
                $query['$filter'] = 'receivedDateTime ge '.now()->parse((string) $config['received_after'])->utc()->format('Y-m-d\\TH:i:s\\Z');
            }
        }

        $folder = rawurlencode((string) ($config['folder'] ?? 'inbox'));

        return $this->get($run, "/me/mailFolders/{$folder}/messages", $config, $query);
    }
}
