<?php

namespace App\Nodes\Integrations\Apollo;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ApolloSearchEmailsNode extends AbstractApolloNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'apollo_search_emails';
    }

    public function name(): string
    {
        return 'Apollo: Search Outreach Emails';
    }

    public function description(): string
    {
        return 'Finds emails sent through sequences by status, sequence, user and date. As a trigger it starts the flow when an email reaches one of the statuses.';
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
                'emailer_message_stats' => Field::list('Statuses', 'delivered, scheduled, drafted, not_opened, opened, clicked, unsubscribed, demoed, bounced, spam_blocked or failed_other.'),
                'emailer_campaign_ids' => Field::list('Sequence ids'),
                'q_keywords' => Field::text('Keywords'),
                'date_from' => Field::advanced(Field::text('From', 'YYYY-MM-DD.')),
                'date_to' => Field::advanced(Field::text('To', 'YYYY-MM-DD.')),
                'emailer_message_date_range_mode' => Field::advanced(Field::select('Dates are when', ['completed_at' => 'Delivered', 'due_at' => 'Scheduled'])),
                'user_ids' => Field::advanced(Field::list('User ids')),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = ['emailer_message_stats' => $this->stringList($config, 'emailer_message_stats'), 'emailer_campaign_ids' => $this->stringList($config, 'emailer_campaign_ids'), 'user_ids' => $this->stringList($config, 'user_ids'), 'emailer_message_date_range[min]' => $config['date_from'] ?? null, 'emailer_message_date_range[max]' => $config['date_to'] ?? null, ...$this->only($config, ['q_keywords', 'emailer_message_date_range_mode']), ...$this->paging($config)];

        return $this->listResult($this->apollo($run, $config, 'GET', '/emailer_messages/search', $query), 'emailer_messages');
    }

    public function triggerName(): string
    {
        return 'Apollo: Outreach email status';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow for each outreach email that reaches one of the chosen statuses, like opened or bounced. Checks every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'emailer_messages';
    }

    /**
     * The same email reaching another status is a new event.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['id'] ?? '').':'.($item['status'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => self::MAX_PAGE_SIZE];
    }
}
