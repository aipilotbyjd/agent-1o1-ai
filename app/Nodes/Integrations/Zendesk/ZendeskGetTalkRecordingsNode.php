<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetTalkRecordingsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_talk_recordings';
    }

    public function name(): string
    {
        return 'Zendesk: Get Talk Recordings';
    }

    public function description(): string
    {
        return 'Finds the call and voicemail recordings on a ticket, from its voice comments, with their download addresses.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $comments = $this->listAll($run, $config, "/tickets/{$id}/comments", 'comments', [], 1000);
        $voice = array_values(array_filter($comments, fn (array $comment): bool => ($comment['type'] ?? null) === 'VoiceComment'));
        $recordings = array_map(fn (array $comment): array => ['comment_id' => $comment['id'] ?? null, 'call_id' => data_get($comment, 'data.call_id'), 'recording_url' => data_get($comment, 'data.recording_url'), 'duration' => data_get($comment, 'data.call_duration'), 'created_at' => $comment['created_at'] ?? null], $voice);

        return ['recordings' => $recordings, 'count' => count($recordings)];
    }
}
