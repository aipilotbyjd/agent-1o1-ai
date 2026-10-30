<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class FreshdeskListNewNotesNode extends AbstractFreshdeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'freshdesk_list_new_notes';
    }

    public function name(): string
    {
        return 'Freshdesk: List New Ticket Notes';
    }

    public function description(): string
    {
        return 'Lists the notes and replies on tickets changed lately. As a trigger it starts the flow for each new one. Checked every few minutes; it reads the 30 most recently changed tickets.';
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
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only tickets changed this recently.', 360)),
                'include_replies' => Field::advanced(Field::boolean('Include replies', 'Off lists only notes.')),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = now()->subMinutes((int) ($config['changed_within_minutes'] ?? 360))->utc()->format('Y-m-d\TH:i:s\Z');
        $tickets = $this->listAll($run, $config, '/tickets', ['updated_since' => $since, 'order_by' => 'updated_at', 'order_type' => 'desc'], 30);
        $replies = filter_var($config['include_replies'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $found = [];
        foreach ($tickets as $ticket) {
            foreach ($this->listAll($run, $config, '/tickets/'.(int) ($ticket['id'] ?? 0).'/conversations', [], 100) as $conversation) {
                if ($replies || (int) ($conversation['source'] ?? 0) === 2) {
                    $found[] = $this->shapeConversation($conversation);
                }
            }
        }
        usort($found, fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
        $found = array_slice($found, 0, $this->limit($config, 100));

        return ['notes' => $found, 'count' => count($found)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'notes';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 500];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Freshdesk: New ticket note';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a note is added to a recently changed ticket. Checked every few minutes.';
    }
}
