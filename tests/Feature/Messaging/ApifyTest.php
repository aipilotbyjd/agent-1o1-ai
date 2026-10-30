<?php

use App\Enums\Agents\ActionEffect;
use App\Enums\Triggers\SubscriptionStatus;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Runs\Run;
use App\Models\Triggers\ProviderWatch;
use App\Models\User;
use App\Nodes\Integrations\Apify\AbstractApifyNode;
use App\Nodes\Integrations\Apify\ApifyAbortRunNode;
use App\Nodes\Integrations\Apify\ApifyCreateScheduleNode;
use App\Nodes\Integrations\Apify\ApifyCreateTaskNode;
use App\Nodes\Integrations\Apify\ApifyCreateWebhookNode;
use App\Nodes\Integrations\Apify\ApifyGetDatasetItemsNode;
use App\Nodes\Integrations\Apify\ApifyGetDatasetSchemaNode;
use App\Nodes\Integrations\Apify\ApifyGetStoreRecordNode;
use App\Nodes\Integrations\Apify\ApifyListActorRunsNode;
use App\Nodes\Integrations\Apify\ApifyRunActorAndGetResultsNode;
use App\Nodes\Integrations\Apify\ApifyRunActorNode;
use App\Nodes\Integrations\Apify\ApifySearchStoreNode;
use App\Nodes\Integrations\Apify\ApifySetStoreRecordNode;
use App\Nodes\Integrations\Apify\ApifyWebFetchNode;
use App\Services\Triggers\Push\ApifySource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ApifyOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/WebDataTestHelpers.php';

/**
 * @param  array<string, mixed>  $routes
 * @param  array<int|string, mixed>|null  $fallback
 */
function fakeApify(array $routes, ?array $fallback = null): void
{
    webDataFake('*', '/v2', $routes, $fallback);
}

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function apifyConfig(array $config = []): array
{
    return ['access_token' => 'apify_api_key', ...$config];
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function apifyRunRow(string $id, string $status = 'SUCCEEDED', array $extra = []): array
{
    return ['id' => $id, 'actId' => 'act1', 'status' => $status, 'startedAt' => '2026-01-31T09:00:00Z', 'finishedAt' => '2026-01-31T09:01:00Z', 'defaultDatasetId' => 'ds1', 'defaultKeyValueStoreId' => 'kv1', 'usageTotalUsd' => 0.1, ...$extra];
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function apifyPage(array $items): array
{
    return ['data' => ['total' => count($items), 'offset' => 0, 'limit' => 100, 'desc' => true, 'count' => count($items), 'items' => $items]];
}

describe('apify nodes', function () {
    it('registers the nodes under the apify connector, each with a sound schema', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'apify_'));

        expect(count($types))->toBeGreaterThanOrEqual(75);

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            $schema = $node->configSchema();

            expect($node)->toBeInstanceOf(AbstractApifyNode::class)
                ->and($node->category())->toBe('apify')
                ->and($node->name())->toStartWith('Apify: ')
                ->and($node->effect([]))->toBeInstanceOf(ActionEffect::class)
                ->and(array_diff($schema['required'], array_keys($schema['properties'])))->toBe([]);
        }
    });

    it('runs every node with every setting filled, only ever calling Apify', function () {
        fakeApify([], ['data' => ['id' => 'x', 'items' => [], 'status' => 'SUCCEEDED', 'defaultDatasetId' => 'ds1']]);
        $registry = app(NodeRegistry::class);
        $run = Run::factory()->create();
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'apify_'));

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            $config = webDataConfigFor($node->configSchema(), ['path' => '/users/me', 'method' => 'POST', 'key' => 'OUTPUT', 'version' => '0.1', 'status' => 'finished']);

            expect($node->execute($run, $config, []))->toBeArray();
        }

        foreach (Http::recorded() as [$request]) {
            expect($request->url())->toMatch('~^https://(api\.apify\.com/v2/|web-fetch\.apify\.actor/)~')->and($request->header('Authorization')[0])->toBe('Bearer the-key');
        }
    });

    it('starts a run at once with its options, reading a / in an Actor name as ~', function () {
        fakeApify(['POST /actors/apify~web-scraper/runs' => ['data' => apifyRunRow('r1', 'READY')]]);

        $output = (new ApifyRunActorNode)->execute(Run::factory()->create(), apifyConfig(['actor_id' => 'apify/web-scraper', 'input' => '{"startUrls":[]}', 'memory' => 1024, 'max_items' => 5]), []);
        $request = webDataRequest('POST /actors/apify~web-scraper/runs', '/v2');

        expect($output)->toMatchArray(['id' => 'r1', 'status' => 'READY', 'finished' => false, 'dataset_id' => 'ds1'])
            ->and(json_decode($request->body(), true))->toBe(['startUrls' => []])
            ->and($request->url())->toContain('memory=1024')->toContain('maxItems=5')->not->toContain('waitForFinish')
            ->and($request->header('Authorization')[0])->toBe('Bearer apify_api_key')
            ->and(fn () => (new ApifyRunActorNode)->execute(Run::factory()->create(), apifyConfig(['actor_id' => 'a/../b']), []))->toThrow(InvalidArgumentException::class);
    });

    it('waits at most a minute for results, and reads them when the run succeeded', function () {
        fakeApify(['POST /actors/act1/runs' => ['data' => apifyRunRow('r1')], 'GET /datasets/ds1/items' => [['title' => 'A'], ['title' => 'B']]]);

        $output = (new ApifyRunActorAndGetResultsNode)->execute(Run::factory()->create(), apifyConfig(['actor_id' => 'act1', 'wait_seconds' => 9999, 'fields' => 'title']), []);

        expect($output)->toMatchArray(['id' => 'r1', 'count' => 2, 'more_coming' => false])
            ->and(webDataRequest('POST /actors/act1/runs', '/v2')->url())->toContain('waitForFinish=60')
            ->and(webDataRequest('GET /datasets/ds1/items', '/v2')->url())->toContain('fields=title');
    });

    it('hands a run still going back by id, with no results yet', function () {
        fakeApify(['POST /actors/act1/runs' => ['data' => apifyRunRow('r1', 'RUNNING')]]);

        $output = (new ApifyRunActorAndGetResultsNode)->execute(Run::factory()->create(), apifyConfig(['actor_id' => 'act1']), []);

        expect($output)->toMatchArray(['id' => 'r1', 'finished' => false, 'items' => [], 'more_coming' => true]);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/items'));
    });

    it('aborts a run gracefully', function () {
        fakeApify(['POST /actor-runs/r1/abort' => ['data' => apifyRunRow('r1', 'ABORTING')]]);

        expect((new ApifyAbortRunNode)->execute(Run::factory()->create(), apifyConfig(['run_id' => 'r1', 'gracefully' => true]), [])['status'])->toBe('ABORTING')
            ->and(webDataRequest('POST /actor-runs/r1/abort', '/v2')->url())->toContain('gracefully=true');
    });

    it('reads dataset items and works out their schema', function () {
        fakeApify(['GET /datasets/ds1/items' => [['title' => 'A', 'price' => 1], ['title' => 'B', 'price' => 1.5, 'tags' => ['x']]]]);

        expect((new ApifyGetDatasetItemsNode)->execute(Run::factory()->create(), apifyConfig(['dataset_id' => 'ds1']), [])['count'])->toBe(2)
            ->and((new ApifyGetDatasetSchemaNode)->execute(Run::factory()->create(), apifyConfig(['dataset_id' => 'ds1']), [])['schema']['properties'])->toBe(['title' => ['type' => ['string']], 'price' => ['type' => ['integer', 'number']], 'tags' => ['type' => ['array']]]);
    });

    it('sets and reads a key-value store record', function () {
        fakeApify(['PUT /key-value-stores/kv1/records/OUTPUT' => null, 'GET /key-value-stores/kv1/records/OUTPUT' => ['a' => 1]]);

        expect((new ApifySetStoreRecordNode)->execute(Run::factory()->create(), apifyConfig(['store_id' => 'kv1', 'key' => 'OUTPUT', 'value' => ['a' => 1]]), []))->toBe(['saved' => true, 'key' => 'OUTPUT'])
            ->and(webDataBody('PUT /key-value-stores/kv1/records/OUTPUT', '/v2'))->toBe(['a' => 1])
            ->and((new ApifyGetStoreRecordNode)->execute(Run::factory()->create(), apifyConfig(['store_id' => 'kv1', 'key' => 'OUTPUT']), [])['value'])->toBe(['a' => 1])
            ->and(fn () => (new ApifyGetStoreRecordNode)->execute(Run::factory()->create(), apifyConfig(['store_id' => 'kv1', 'key' => 'a/b']), []))->toThrow(InvalidArgumentException::class);
    });

    it('creates tasks, schedules and webhooks in Apify\'s own names', function () {
        fakeApify(['POST /actor-tasks' => ['data' => ['id' => 't1']], 'POST /schedules' => ['data' => ['id' => 's1']], 'POST /webhooks' => ['data' => ['id' => 'w1']]]);
        $run = Run::factory()->create();

        (new ApifyCreateTaskNode)->execute($run, apifyConfig(['actor_id' => 'act1', 'name' => 'daily', 'input' => ['a' => 1], 'memory' => 512]), []);
        (new ApifyCreateScheduleNode)->execute($run, apifyConfig(['name' => 'mornings', 'cron' => '0 9 * * *', 'actions' => [['type' => 'RUN_ACTOR', 'actorId' => 'act1']], 'enabled' => true]), []);
        (new ApifyCreateWebhookNode)->execute($run, apifyConfig(['request_url' => 'https://hooks.io/x', 'event_types' => 'ACTOR.RUN.FAILED', 'task_id' => 't1']), []);

        expect(webDataBody('POST /actor-tasks', '/v2'))->toBe(['actId' => 'act1', 'name' => 'daily', 'input' => ['a' => 1], 'options' => ['memoryMbytes' => 512]])
            ->and(webDataBody('POST /schedules', '/v2'))->toBe(['name' => 'mornings', 'cronExpression' => '0 9 * * *', 'isEnabled' => true, 'actions' => [['type' => 'RUN_ACTOR', 'actorId' => 'act1']]])
            ->and(webDataBody('POST /webhooks', '/v2'))->toBe(['requestUrl' => 'https://hooks.io/x', 'eventTypes' => ['ACTOR.RUN.FAILED'], 'condition' => ['actorTaskId' => 't1']])
            ->and(fn () => (new ApifyCreateWebhookNode)->execute($run, apifyConfig(['request_url' => 'https://hooks.io/x', 'event_types' => 'ACTOR.RUN.FAILED']), []))->toThrow(InvalidArgumentException::class, 'Actor or task');
    });

    it('searches the Store and fetches a page through the Actor', function () {
        Http::preventStrayRequests();
        fakeApify(['GET /store' => apifyPage([['id' => 'a1', 'username' => 'apify', 'name' => 'web-scraper', 'title' => 'Web Scraper']]), 'POST /' => ['markdown' => '# Hi', 'fetch' => ['url' => 'https://a.io']]]);

        expect((new ApifySearchStoreNode)->execute(Run::factory()->create(), apifyConfig(['search' => 'scraper']), [])['actors'][0]['name'])->toBe('apify/web-scraper')
            ->and((new ApifyWebFetchNode)->execute(Run::factory()->create(), apifyConfig(['url' => 'https://a.io', 'formats' => 'markdown']), [])['markdown'])->toBe('# Hi')
            ->and(webDataRequest('POST /', '/v2')->url())->toBe('https://web-fetch.apify.actor/');
    });

    it('explains an error with a hint', function (int $status, string $hint) {
        fakeApify(['GET /actors/act1/runs' => fn () => Http::response(['error' => ['type' => 'x', 'message' => 'Nope']], $status)]);

        expect(fn () => (new ApifyListActorRunsNode)->execute(Run::factory()->create(), apifyConfig(['actor_id' => 'act1']), []))->toThrow(RuntimeException::class, $hint);
    })->with([[401, 'Reconnect Apify'], [402, 'out of credit'], [403, 'not allowed'], [429, 'rate limiting']]);
});

describe('apify options and connection', function () {
    it('lists Actors as username/name and filters by the search', function () {
        fakeApify(['GET /actors' => apifyPage([['id' => 'a1', 'username' => 'ada', 'name' => 'scraper', 'title' => 'Scraper'], ['id' => 'a2', 'username' => 'ada', 'name' => 'other']])]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

        $page = app(ApifyOptions::class)->load('apify.actors', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'apify_api_key', 'scrap'));

        expect($page->toArray()['options'])->toBe([['value' => 'a1', 'label' => 'ada/scraper', 'description' => 'Scraper']]);
    });

    it('answers every source', function (string $source, string $path) {
        fakeApify([$path => apifyPage([['id' => 'x1', 'name' => 'one']])]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

        expect(app(ApifyOptions::class)->load($source, new NodeOptionsQuery($workspace, User::factory()->create(), [], 'apify_api_key'))->toArray()['options'][0]['value'])->toBe('x1');
    })->with([['apify.tasks', 'GET /actor-tasks'], ['apify.datasets', 'GET /datasets'], ['apify.key_value_stores', 'GET /key-value-stores'], ['apify.schedules', 'GET /schedules'], ['apify.webhooks', 'GET /webhooks']]);

    it('checks the token and shows whose it is', function () {
        fakeApify(['GET /users/me' => ['data' => ['username' => 'ada', 'email' => 'ada@acme.com']]]);
        expect(webDataCredentialCheck('apify'))->toMatchArray(['ok' => true, 'account' => 'ada']);

        fakeApify(['GET /users/me' => fn () => Http::response(['error' => ['message' => 'bad']], 401)]);
        expect(webDataCredentialCheck('apify')['ok'])->toBeFalse();
    });
});

describe('apify triggers', function () {
    it('polls finished runs: a baseline first, then one run for each, leaving out those still going', function () {
        $trigger = webDataTrigger('apify', 'apify_list_actor_runs', ['actor_id' => 'act1'], false);

        fakeApify(['GET /actors/act1/runs' => apifyPage([apifyRunRow('r1')])]);
        webDataPoll($trigger);
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        fakeApify(['GET /actors/act1/runs' => apifyPage([apifyRunRow('r3', 'RUNNING'), apifyRunRow('r2', 'FAILED'), apifyRunRow('r1')])]);
        webDataPoll($trigger);

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->all())->toBe(['r2'])
            ->and(urldecode(webDataRequest('GET /actors/act1/runs', '/v2')->url()))->toContain('status=SUCCEEDED,FAILED,TIMED-OUT,ABORTED');
    });

    it('polls builds and every run in the account', function () {
        $builds = webDataTrigger('apify', 'apify_list_actor_builds', ['actor_id' => 'act1'], false);
        $all = webDataTrigger('apify', 'apify_list_runs', [], false);

        fakeApify(['GET /actors/act1/builds' => apifyPage([['id' => 'b1', 'status' => 'SUCCEEDED']]), 'GET /actor-runs' => apifyPage([apifyRunRow('r1')])]);
        webDataPoll($builds);
        webDataPoll($all);

        fakeApify(['GET /actors/act1/builds' => apifyPage([['id' => 'b2', 'status' => 'FAILED'], ['id' => 'b1', 'status' => 'SUCCEEDED']]), 'GET /actor-runs' => apifyPage([apifyRunRow('r2'), apifyRunRow('r1')])]);
        webDataPoll($builds);
        webDataPoll($all);

        expect($builds->events()->where('status', 'queued')->sole()->payload['id'])->toBe('b2')
            ->and($all->events()->where('status', 'queued')->sole()->payload['id'])->toBe('r2');
    });

    it('registers a webhook for an Actor with a token URL, and a delivery starts a poll once', function () {
        fakeApify(['POST /webhooks' => ['data' => ['id' => 'wh_1']]]);
        $trigger = webDataTrigger('apify', 'apify_list_actor_runs', ['actor_id' => 'act1']);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('actorId:act1:RUN:finished')
            ->and($watch->external_id)->toBe('wh_1')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(webDataBody('POST /webhooks', '/v2'))->toBe(['requestUrl' => url('/api/hooks/apps/apify').'?token='.$watch->secret, 'eventTypes' => ['ACTOR.RUN.SUCCEEDED', 'ACTOR.RUN.FAILED', 'ACTOR.RUN.ABORTED', 'ACTOR.RUN.TIMED_OUT'], 'condition' => ['actorId' => 'act1']]);

        $delivery = ['eventType' => 'ACTOR.RUN.SUCCEEDED', 'eventData' => ['actorRunId' => 'r1'], 'resource' => ['id' => 'r1']];
        test()->postJson('/api/hooks/apps/apify?token='.$watch->secret, $delivery, ['X-Apify-Webhook-Dispatch-Id' => 'd1'])->assertOk();
        test()->postJson('/api/hooks/apps/apify?token='.$watch->secret, $delivery, ['X-Apify-Webhook-Dispatch-Id' => 'd1'])->assertOk();
        test()->postJson('/api/hooks/apps/apify?token=nope', $delivery)->assertUnauthorized();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('starts nothing from a test event', function () {
        fakeApify(['POST /webhooks' => ['data' => ['id' => 'wh_1']]]);
        webDataTrigger('apify', 'apify_list_actor_runs', ['actor_id' => 'act1']);

        test()->postJson('/api/hooks/apps/apify?token='.ProviderWatch::query()->sole()->secret, ['eventType' => 'TEST'], ['X-Apify-Webhook-Dispatch-Id' => 'd2'])->assertOk();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('resolves a task named username~name to its id, and picks the events of an outcome', function () {
        fakeApify(['GET /actor-tasks/ada~daily' => ['data' => ['id' => 't1']], 'POST /webhooks' => ['data' => ['id' => 'wh_2']]]);
        webDataTrigger('apify', 'apify_list_task_runs', ['task_id' => 'ada/daily', 'status' => 'FAILED']);

        expect(ProviderWatch::query()->sole()->resource_key)->toBe('actorTaskId:ada~daily:RUN:FAILED')
            ->and(webDataBody('POST /webhooks', '/v2'))->toMatchArray(['eventTypes' => ['ACTOR.RUN.FAILED'], 'condition' => ['actorTaskId' => 't1']]);
    });

    it('watches builds with build events, and does not watch "any run"', function () {
        fakeApify(['POST /webhooks' => ['data' => ['id' => 'wh_3']]]);
        webDataTrigger('apify', 'apify_list_actor_builds', ['actor_id' => 'act1', 'status' => 'SUCCEEDED']);
        webDataTrigger('apify', 'apify_list_runs');

        expect(webDataBody('POST /webhooks', '/v2')['eventTypes'])->toBe(['ACTOR.BUILD.SUCCEEDED'])->and(ProviderWatch::query()->count())->toBe(1);
    });

    it('makes the webhook again when Apify lost it, and removes it with the watch', function () {
        fakeApify(['POST /webhooks' => ['data' => ['id' => 'wh_1']]]);
        webDataTrigger('apify', 'apify_list_actor_runs', ['actor_id' => 'act1']);
        $watch = ProviderWatch::query()->sole();
        $source = app(ApifySource::class);

        fakeApify(['GET /webhooks/wh_1' => fn () => Http::response([], 404), 'POST /webhooks' => ['data' => ['id' => 'wh_9']]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe('wh_9');

        fakeApify(['GET /webhooks/wh_9' => ['data' => ['id' => 'wh_9']]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');

        fakeApify(['DELETE /webhooks/wh_9' => null]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
