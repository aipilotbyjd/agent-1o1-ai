<?php

namespace App\Nodes\Integrations\Gmail;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GmailListMessagesNode extends AbstractGmailNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'gmail_list_messages';
    }

    public function name(): string
    {
        return 'Gmail: List Messages';
    }

    public function description(): string
    {
        return 'Lists messages in the inbox matching a query.';
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
                'query' => Field::text('Search', 'Gmail search syntax, same as the Gmail search box.', 'from:boss@example.com is:unread newer_than:1d'),
                'max_results' => Field::integer('Max results', null, 10, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, '/users/me/messages', $config, [
            'q' => $config['query'] ?? '',
            'maxResults' => $config['max_results'] ?? 10,
        ]);
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'messages';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Gmail: New email';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a new email matching the search arrives.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 1;
    }

    /**
     * The list call returns only ids: fetch each new message's headers and
     * snippet so the run has something to work with.
     */
    public function expandTriggerItem(Run $run, array $config, array $item): array
    {
        $message = $this->get($run, '/users/me/messages/'.rawurlencode((string) $item['id']), $config, ['format' => 'metadata']);
        $headers = collect($message['payload']['headers'] ?? [])
            ->mapWithKeys(fn (array $header): array => [strtolower((string) ($header['name'] ?? '')) => $header['value'] ?? null]);

        return [
            'id' => $message['id'] ?? $item['id'],
            'thread_id' => $message['threadId'] ?? ($item['threadId'] ?? null),
            'from' => $headers->get('from'),
            'to' => $headers->get('to'),
            'subject' => $headers->get('subject'),
            'date' => $headers->get('date'),
            'snippet' => $message['snippet'] ?? null,
            'label_ids' => $message['labelIds'] ?? [],
        ];
    }
}
