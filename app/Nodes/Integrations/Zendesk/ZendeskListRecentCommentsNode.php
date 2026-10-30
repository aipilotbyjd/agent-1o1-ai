<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ZendeskListRecentCommentsNode extends AbstractZendeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'zendesk_list_recent_comments';
    }

    public function name(): string
    {
        return 'Zendesk: List Recent Comments';
    }

    public function description(): string
    {
        return 'Lists comments added to any ticket lately, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes. Needs an admin.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $audits = $this->listAll($run, $config, '/ticket_audits', 'audits', ['page' => ['size' => 100]], 300);
        $comments = [];
        foreach ($audits as $audit) {
            foreach ((array) ($audit['events'] ?? []) as $event) {
                if (is_array($event) && ($event['type'] ?? null) === 'Comment') {
                    $comments[] = [...$this->shapeComment($event), 'ticket_id' => $audit['ticket_id'] ?? null, 'created_at' => $audit['created_at'] ?? null];
                }
            }
        }
        $comments = array_slice($comments, 0, $this->limit($config, 100));

        return ['comments' => $comments, 'count' => count($comments)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'comments';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 1000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Zendesk: New comment';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a comment is added to any ticket. Instant, with a check every few minutes. Needs an admin.';
    }
}
