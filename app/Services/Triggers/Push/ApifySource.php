<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Apify triggers through its webhooks.
 *
 * One webhook per Actor or task and outcome, shared by the triggers that watch it.
 * A delivery only makes the trigger's own poll look now, so the URL's random token
 * (kept hashed) is the check. Apify's webhooks need an Actor or a task to be about,
 * so the "any run" trigger polls.
 */
class ApifySource extends AbstractTokenWatchedSource
{
    private const string API = 'https://api.apify.com/v2';

    /**
     * Each reader node, and the setting that names what it reads.
     *
     * @var array<string, array{setting: string, condition: string, event: string}>
     */
    private const array NODES = [
        'apify_list_actor_runs' => ['setting' => 'actor_id', 'condition' => 'actorId', 'event' => 'RUN'],
        'apify_list_task_runs' => ['setting' => 'task_id', 'condition' => 'actorTaskId', 'event' => 'RUN'],
        'apify_list_actor_builds' => ['setting' => 'actor_id', 'condition' => 'actorId', 'event' => 'BUILD'],
    ];

    /**
     * The webhook events behind each outcome a node can ask for.
     *
     * @var array<string, list<string>>
     */
    private const array OUTCOMES = [
        'finished' => ['SUCCEEDED', 'FAILED', 'ABORTED', 'TIMED_OUT'],
        'SUCCEEDED' => ['SUCCEEDED'],
        'FAILED' => ['FAILED'],
        'ABORTED' => ['ABORTED'],
        'TIMED-OUT' => ['TIMED_OUT'],
        'any' => ['CREATED'],
    ];

    public function provider(): string
    {
        return 'apify';
    }

    protected function nodeTypes(): array
    {
        return array_keys(self::NODES);
    }

    /**
     * `actor:<id>:RUN:finished`. A setting filled from an earlier step can't be watched.
     */
    public function watchKeyFor(Trigger $trigger): ?string
    {
        $node = self::NODES[$trigger->config['node_type'] ?? ''] ?? null;
        $config = $this->nodeConfig($trigger);
        $id = str_replace('/', '~', trim((string) ($config[$node['setting'] ?? ''] ?? '')));
        $outcome = (string) ($config['status'] ?? 'finished');

        if ($node === null || preg_match('/^[A-Za-z0-9._~-]{1,200}$/', $id) !== 1 || ! isset(self::OUTCOMES[$outcome])) {
            return null;
        }

        return implode(':', [$node['condition'], $id, $node['event'], $outcome]);
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Someone may have removed the webhook: make it again if so.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $response = $this->apifyHttp($this->credentialOf($watch))->get(self::API.'/webhooks/'.rawurlencode((string) $watch->external_id));

        if ($response->status() === 404) {
            $this->register($watch);

            return;
        }

        $this->ok($response);
        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $response = $this->apifyHttp($this->credentialOf($watch))->delete(self::API.'/webhooks/'.rawurlencode((string) $watch->external_id));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Make the webhook for the watch's Actor or task and events, with a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        [$condition, $id, $kind, $outcome] = explode(':', (string) $watch->resource_key, 4);
        $http = $this->apifyHttp($this->credentialOf($watch));
        $resourceId = $this->resolvedId($http, $condition, $id);
        $events = array_map(fn (string $event): string => "ACTOR.{$kind}.{$event}", self::OUTCOMES[$outcome] ?? self::OUTCOMES['finished']);

        $this->registerUrl($watch, function (string $url) use ($watch, $http, $condition, $resourceId, $events): void {
            $response = $this->ok($http->post(self::API.'/webhooks', ['requestUrl' => $url, 'eventTypes' => $events, 'condition' => [$condition => $resourceId]]));

            if (! filled($response->json('data.id'))) {
                throw new WatchRefusedException('Apify did not create the webhook.');
            }

            $watch->external_id = (string) $response->json('data.id');
        });

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * A webhook condition takes an id, and a node may hold `username~name`.
     */
    private function resolvedId(PendingRequest $http, string $condition, string $id): string
    {
        if (! str_contains($id, '~')) {
            return $id;
        }

        $path = $condition === 'actorId' ? 'actors' : 'actor-tasks';

        return (string) $this->ok($http->get(self::API."/{$path}/".rawurlencode($id)))->json('data.id');
    }

    private function apifyHttp(ConnectorCredential $credential): PendingRequest
    {
        return Http::withToken($this->tokens->accessToken($credential))->acceptJson()->timeout(20);
    }
}
