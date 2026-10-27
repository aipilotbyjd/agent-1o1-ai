<?php

namespace App\Nodes\Integrations\Slack;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class SlackGetChannelHistoryNode extends AbstractSlackNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'slack_get_channel_history';
    }

    public function name(): string
    {
        return 'Slack: Get Channel History';
    }

    public function description(): string
    {
        return 'Fetches recent messages from a Slack channel.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'The channel to use. Pick one, or enter a channel ID.', 'C0123456789'),
                'limit' => Field::integer('Limit', 'Maximum messages to return.', 100, 1, 1000),
                'oldest' => Field::advanced(Field::text('Oldest', 'Only messages after this Unix timestamp.', '1700000000')),
                'latest' => Field::advanced(Field::text('Latest', 'Only messages before this Unix timestamp.', '1700000000')),
                'ignore_bot_messages' => Field::boolean('Ignore bot messages', 'When used as a trigger, skip messages from bots and integrations. Turn this on if the workflow posts back to the same channel, or it can trigger itself.', true),
                'ignore_replies' => Field::boolean('Ignore replies', 'When used as a trigger, skip replies inside threads and only start on new top-level messages.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, 'conversations.history', $config, array_filter([
            'channel' => $config['channel'],
            'limit' => $config['limit'] ?? null,
            'oldest' => $config['oldest'] ?? null,
            'latest' => $config['latest'] ?? null,
        ], fn ($value) => $value !== null));
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'messages';
    }

    /**
     * The messages a trigger poll may start runs for, minus bot messages and
     * thread replies when those are switched off.
     */
    public function triggerItems(array $output, array $config): array
    {
        $ignoreBots = ($config['ignore_bot_messages'] ?? true) !== false;
        $ignoreReplies = ($config['ignore_replies'] ?? false) === true;

        return array_values(array_filter(
            (array) data_get($output, 'messages', []),
            fn (mixed $message): bool => is_array($message)
                && ! ($ignoreBots && (isset($message['bot_id']) || ($message['subtype'] ?? null) === 'bot_message'))
                && ! ($ignoreReplies && isset($message['thread_ts']) && $message['thread_ts'] !== ($message['ts'] ?? null)),
        ));
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Slack: New message';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a new message is posted in the channel.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 1;
    }

    /**
     * An item's stable id when used as a trigger.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['ts'] ?? '');
    }
}
