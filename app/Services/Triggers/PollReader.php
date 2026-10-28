<?php

namespace App\Services\Triggers;

use App\Contracts\SupportsTriggerMode;
use App\Exceptions\ConnectorException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Services\Connectors\ConnectorTokens;
use App\Services\Http\GuardedHttp;
use App\Services\Workflows\NodeRegistry;
use Closure;
use RuntimeException;

/**
 * Reads the items a polling trigger sees right now, without recording or
 * starting anything.
 *
 * Items come from either:
 * - a plain endpoint, described by the trigger's config (`url`, `method`,
 *   `items_path`, `id_path`, `cursor_param`, `cursor_path`), sent with the
 *   connected account's token as a Bearer header when one is set; or
 * - a reader node switched on as a flow trigger (`config.node_type`), whose
 *   own execute() does the reading.
 *
 * Shared by PollTrigger (which fires runs for the new items) and
 * TriggerSampler (which only shows them).
 */
class PollReader
{
    public function __construct(
        private readonly GuardedHttp $http,
        private readonly ConnectorTokens $tokens,
        private readonly NodeRegistry $registry,
    ) {}

    /**
     * The trigger's items, oldest first, plus how to get each one's id,
     * payload and cursor value.
     *
     * @return array{0: list<array<string, mixed>>, 1: Closure(array<string, mixed>): string, 2: Closure(array<string, mixed>): array<string, mixed>, 3: Closure(array<string, mixed>): mixed}
     */
    public function read(Trigger $trigger): array
    {
        $config = array_merge((array) ($trigger->preset?->config ?? []), (array) $trigger->config);

        return isset($config['node_type'])
            ? $this->readerItems($trigger, $config)
            : $this->urlItems($trigger, $config);
    }

    /**
     * Read items from a plain endpoint.
     *
     * Returns the items, plus how to get each one's id, payload and cursor
     * value.
     *
     * @param  array<string, mixed>  $config
     * @return array{0: list<array<string, mixed>>, 1: Closure(array<string, mixed>): string, 2: Closure(array<string, mixed>): array<string, mixed>, 3: Closure(array<string, mixed>): mixed}
     */
    private function urlItems(Trigger $trigger, array $config): array
    {
        $url = $config['url'] ?? throw new RuntimeException("Polling trigger {$trigger->id} has no URL.");

        $response = $this->http->send(strtolower((string) ($config['method'] ?? 'get')), $url, headers: $this->authHeaders($trigger), query: $this->cursorQuery($trigger, $config));
        $response->throw();

        $items = array_values(array_filter((array) (data_get($response->json(), $config['items_path'] ?? null) ?? []), 'is_array'));

        // Runs start oldest first, so an endpoint that lists the newest item
        // first is turned around.
        if (($config['newest_first'] ?? false) === true) {
            $items = array_reverse($items);
        }

        $idPath = $config['id_path'] ?? 'id';

        return [
            $items,
            fn (array $item): string => (string) data_get($item, $idPath),
            fn (array $item): array => $item,
            fn (array $item): mixed => data_get($item, $config['cursor_path'] ?? $idPath),
        ];
    }

    /**
     * Read items through a reader node switched on as a flow trigger.
     *
     * The node's own execute() does the reading, as the trigger's owner,
     * through the node's connected account. Returns the same shape as
     * urlItems().
     *
     * @param  array<string, mixed>  $config
     * @return array{0: list<array<string, mixed>>, 1: Closure(array<string, mixed>): string, 2: Closure(array<string, mixed>): array<string, mixed>, 3: Closure(array<string, mixed>): mixed}
     */
    private function readerItems(Trigger $trigger, array $config): array
    {
        $node = $this->registry->resolve((string) $config['node_type']);

        if (! $node instanceof SupportsTriggerMode) {
            throw new RuntimeException("Node type [{$config['node_type']}] can't act as a trigger.");
        }

        $nodeConfig = (array) ($config['node_config'] ?? []);

        if ($trigger->credential_id !== null) {
            $nodeConfig['credential_id'] = $trigger->credential_id;
        }

        // Never saved: it only tells the node which workspace and person it
        // acts for, which is all a real run gives ResolvesConnectorCredential.
        $run = new Run(['workspace_id' => $trigger->workspace_id, 'triggered_by' => $trigger->actingUser()?->id]);

        $items = $node->triggerItems($node->execute($run, $node->triggerReadConfig($nodeConfig), []), $nodeConfig);

        // Runs start oldest first, so a burst is processed in the order it
        // happened.
        if ($node->triggerListsNewestFirst()) {
            $items = array_reverse($items);
        }

        return [
            $items,
            fn (array $item): string => $node->triggerItemId($item),
            fn (array $item): array => $node->expandTriggerItem($run, $nodeConfig, $item),
            fn (array $item): mixed => $node->triggerItemId($item),
        ];
    }

    /**
     * The Authorization header for the trigger's connected account, if any.
     *
     * Only an account the trigger's owner could pick themselves: a team
     * account or their own personal one. TriggerNodeSync checks this at
     * publish; it's checked again here in case the account changed since.
     *
     * @return array<string, string>
     *
     * @throws ConnectorException when the account can't produce a token
     */
    private function authHeaders(Trigger $trigger): array
    {
        if ($trigger->credential_id === null) {
            return [];
        }

        $credential = ConnectorCredential::query()
            ->where('workspace_id', $trigger->workspace_id)
            ->find($trigger->credential_id);

        $owner = $trigger->actingUser();

        if ($credential === null || ($owner !== null && ! $credential->isVisibleTo($owner))) {
            throw new ConnectorException("Polling trigger {$trigger->id} has no usable account.");
        }

        return ['Authorization' => 'Bearer '.$this->tokens->accessToken($credential)];
    }

    /**
     * The query parameter that asks the endpoint for items after the last
     * one seen, when the trigger sets `cursor_param`.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function cursorQuery(Trigger $trigger, array $config): array
    {
        $cursorParam = $config['cursor_param'] ?? null;
        $cursorValue = $trigger->poll_cursor['value'] ?? null;

        if ($cursorParam === null || $cursorValue === null) {
            return [];
        }

        return [$cursorParam => $cursorValue];
    }
}
