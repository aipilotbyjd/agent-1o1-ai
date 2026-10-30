<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskMergeTicketsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_merge_tickets';
    }

    public function name(): string
    {
        return 'Zendesk: Merge Tickets';
    }

    public function description(): string
    {
        return 'Merges tickets into one target ticket and closes the sources. Answers with a job to check.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'source_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Target ticket id', 'The ticket that stays.'),
                'source_ids' => Field::text('Tickets to merge', 'Ticket numbers separated by commas (at most 5).', '101, 102'),
                'target_comment' => Field::advanced(Field::textarea('Comment on the target')),
                'source_comment' => Field::advanced(Field::textarea('Comment on the merged tickets')),
                'public' => Field::advanced(Field::boolean('Public comments')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $public = filter_var($config['public'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $job = $this->zendesk($run, $config, 'POST', "/tickets/{$id}/merge", [], $this->present(['ids' => $this->idList($config, 'source_ids', 'The tickets to merge', 5), 'target_comment' => $this->text($config, 'target_comment'), 'source_comment' => $this->text($config, 'source_comment'), 'target_comment_is_public' => $public, 'source_comment_is_public' => $public]));

        return ['merged' => true, 'id' => (int) $id, 'job_status' => $job['job_status'] ?? null];
    }
}
