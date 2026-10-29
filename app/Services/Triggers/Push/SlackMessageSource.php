<?php

namespace App\Services\Triggers\Push;

use App\Contracts\SupportsTriggerMode;
use App\Contracts\Triggers\ItemPushSource;
use App\Exceptions\ConnectorException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Services\Connectors\ConnectorTokens;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

/**
 * "New message in a Slack channel" over the Events API.
 *
 * Events arrive for a whole Slack team, so the routing key is the team id and
 * the channel and the node's filters are applied here, per trigger. Whether an
 * item passes is decided by the Slack node's own trigger filter, so push and
 * poll can never disagree about it.
 */
class SlackMessageSource implements ItemPushSource
{
    private const string MESSAGES_NODE = 'slack_get_channel_history';

    private const string REACTIONS_NODE = 'slack_reaction_added';

    public function __construct(
        private readonly NodeRegistry $registry,
        private readonly ConnectorTokens $tokens,
    ) {}

    /**
     * The provider key.
     */
    public function provider(): string
    {
        return 'slack';
    }

    /**
     * On when the Slack push switch is on and the platform app's signing
     * secret is set.
     */
    public function isEnabled(): bool
    {
        return (bool) config('triggers.push.slack.enabled') && filled(config('triggers.push.slack.signing_secret'));
    }

    /**
     * A Slack channel-message or reaction trigger reading through a connected account.
     */
    public function supports(Trigger $trigger): bool
    {
        return in_array($trigger->config['node_type'] ?? null, [self::MESSAGES_NODE, self::REACTIONS_NODE], true) && $trigger->credential_id !== null;
    }

    /**
     * The Slack team id of the trigger's account, learned once with
     * `auth.test` and kept on the account.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        $credential = ConnectorCredential::query()
            ->where('workspace_id', $trigger->workspace_id)
            ->find($trigger->credential_id);

        if ($credential === null) {
            return null;
        }

        $known = $credential->data['team_id'] ?? null;

        if (filled($known)) {
            return (string) $known;
        }

        $response = Http::withToken($this->tokens->accessToken($credential))
            ->timeout(15)
            ->post('https://slack.com/api/auth.test');

        $teamId = $response->json('team_id');

        if ($response->json('ok') !== true || ! is_string($teamId) || $teamId === '') {
            throw new ConnectorException('Slack did not accept the connected account: '.($response->json('error') ?? 'unknown error').'. Reconnect Slack.');
        }

        $credential->update(['data' => [...$credential->data, 'team_id' => $teamId]]);

        return $teamId;
    }

    /**
     * Slack only sends channel messages to an app that is in the channel, so
     * say so when it is not.
     */
    public function accessWarning(Trigger $trigger): ?string
    {
        $credential = ConnectorCredential::query()
            ->where('workspace_id', $trigger->workspace_id)
            ->find($trigger->credential_id);
        $channel = $trigger->config['node_config']['channel'] ?? null;

        if ($credential === null || ! filled($channel)) {
            return null;
        }

        try {
            $response = Http::withToken($this->tokens->accessToken($credential))
                ->timeout(15)
                ->get('https://slack.com/api/conversations.info', ['channel' => $channel]);
        } catch (\Throwable) {
            return null;
        }

        if ($response->json('ok') !== true) {
            return null;
        }

        $name = $response->json('channel.name');

        return $response->json('channel.is_member') === false
            ? 'Add the app to '.(is_string($name) ? "#{$name}" : 'the channel').' so it can see new messages. Until then it checks on a timer.'
            : null;
    }

    /**
     * Whether the message is in the trigger's channel and passes the node's
     * bot, reply and channel-event filters.
     */
    public function accepts(Trigger $trigger, array $item): bool
    {
        $nodeConfig = (array) ($trigger->config['node_config'] ?? []);
        $nodeType = (string) ($trigger->config['node_type'] ?? '');
        $isReaction = isset($item['emoji']);

        // A message trigger takes messages, a reaction trigger takes reactions.
        if ($isReaction !== ($nodeType === self::REACTIONS_NODE)) {
            return false;
        }

        if (($item['channel'] ?? null) !== ($nodeConfig['channel'] ?? null)) {
            return false;
        }

        $node = $this->registry->resolve($nodeType);

        if (! $node instanceof SupportsTriggerMode) {
            return false;
        }

        return $node->triggerItems([$isReaction ? 'reactions' : 'messages' => [$item]], $nodeConfig) !== [];
    }

    /**
     * A message event as the history call would list it, plus its channel.
     */
    public function itemFor(array $event): array
    {
        if (($event['type'] ?? null) === 'reaction_added') {
            return array_filter([
                'ts' => $event['item']['ts'] ?? null,
                'channel' => $event['item']['channel'] ?? null,
                'emoji' => $event['reaction'] ?? null,
                'user' => $event['user'] ?? null,
                'item_user' => $event['item_user'] ?? null,
                'event_ts' => $event['event_ts'] ?? null,
            ], fn (mixed $value): bool => $value !== null);
        }

        return array_filter(
            array_intersect_key($event, array_flip([
                'ts', 'text', 'user', 'channel', 'thread_ts', 'bot_id', 'subtype', 'team', 'files', 'attachments', 'blocks', 'client_msg_id', 'type',
            ])),
            fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * The message timestamp (or, for a reaction, message:emoji:person), as in the poll path.
     */
    public function itemId(array $item): string
    {
        // A reaction is named message, emoji and person, as the poll names it.
        return isset($item['emoji'])
            ? ($item['ts'] ?? '').':'.$item['emoji'].':'.($item['user'] ?? '')
            : (string) ($item['ts'] ?? '');
    }
}
