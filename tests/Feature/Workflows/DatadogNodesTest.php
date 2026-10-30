<?php

use App\Enums\Connectors\ConnectorAuthType;
use App\Enums\Triggers\SubscriptionStatus;
use App\Exceptions\ConnectorException;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Nodes\NodeCategory;
use App\Models\Runs\Run;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Datadog\AbstractDatadogNode;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Triggers\Push\DatadogSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\DatadogOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Database\Seeders\AppNodeCategorySeeder;
use Database\Seeders\ConnectorSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function datadogData(array $data = []): array
{
    return ['api_key' => 'dd-api', 'app_key' => 'dd-app', 'site' => 'us1', ...$data];
}

/**
 * A run with a connected Datadog account in its workspace.
 *
 * @param  array<string, mixed>  $data
 */
function datadogRun(array $data = []): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'datadog') ?? Connector::factory()->create(['key' => 'datadog']))->id,
        'data' => datadogData($data),
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Datadog. A key is "METHOD /path"; the value is the JSON body, or a closure
 * returning a response. A route nobody answers is a 404, so a node that calls
 * something unexpected fails.
 *
 * @param  array<string, mixed>  $routes
 */
function datadogFake(array $routes = []): void
{
    Http::swap(new Factory);
    Http::fake(['*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['errors' => ["Nothing fakes {$key}"]], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function datadogRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.parse_url($pair[0]->url(), PHP_URL_PATH) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<mixed>
 */
function datadogBody(string $route): array
{
    return (array) json_decode(datadogRequest($route)->body(), true);
}

/**
 * The query of a call, undecoded names and all.
 *
 * @return array<string, string>
 */
function datadogQuery(string $route): array
{
    $query = [];

    foreach (explode('&', (string) parse_url(datadogRequest($route)->url(), PHP_URL_QUERY)) as $pair) {
        [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
        $query[rawurldecode($name)] = rawurldecode($value);
    }

    return $query;
}

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function datadogExec(string $type, array $config = [], ?Run $run = null): array
{
    return app(NodeRegistry::class)->resolve($type)->execute($run ?? datadogRun(), $config, []);
}

/**
 * Every setting any node needs, valid for all of them.
 *
 * @return array<string, mixed>
 */
function datadogEverySetting(): array
{
    return [
        'monitor_id' => '123', 'monitor_ids' => ['123'], 'event_id' => 'abc', 'dashboard_id' => 'abc-def-ghi', 'list_id' => '5', 'dashboard_ids' => ['abc-def-ghi'],
        'downtime_id' => '00000000-0000-1234-0000-000000000000', 'incident_id' => '00000000-aaaa-0000-0000-000000000000', 'host' => 'web-1', 'tags' => ['env:prod'],
        'slo_id' => '12341234123412341234123412341234', 'ids' => ['12341234123412341234123412341234'], 'correction_id' => 'c1', 'public_id' => 'abc-def-ghi', 'public_ids' => ['abc-def-ghi'],
        'result_id' => '999', 'notebook_id' => '7', 'user_id' => 'u1', 'team_id' => 't1', 'name' => 'A name', 'title' => 'A title', 'type' => 'metric alert', 'query' => 'status:alert',
        'handle' => 'payments', 'email' => 'ada@example.com', 'metric' => 'system.cpu.user', 'value' => '1.5', 'values' => ['1', '2'], 'message' => 'Hello', 'check' => 'app.ok', 'status' => '0',
        'thresholds' => [['target' => 99.9, 'timeframe' => '30d']], 'locations' => ['aws:eu-central-1'], 'config' => ['request' => ['url' => 'https://example.com']], 'options' => ['tick_every' => 300],
        'signal_id' => 'sig1', 'state' => 'open', 'issue_id' => 'iss1', 'case_id' => 'case1', 'comment' => 'A comment', 'project_id' => 'p1', 'type_id' => 'ty1', 'url' => 'https://example.com/hook',
        'content' => 'A todo', 'category' => 'Scheduled Maintenance', 'start' => '2026-01-01T00:00:00Z', 'scope' => 'env:prod', 'service' => 'web', 'trace_id' => 'abc123', 'path' => '/api/v1/org', 'method' => 'GET', 'changed_resource' => 'flag',
    ];
}

/**
 * A published trigger on a Datadog reader.
 *
 * @param  array<string, mixed>  $config
 * @param  array<string, mixed>  $routes
 */
function datadogTrigger(string $nodeType, array $config = [], array $routes = [], bool $sync = false): Trigger
{
    // A faked PollTrigger would swallow dispatch_sync too, so it is only faked where a delivery is what's watched.
    Queue::fake($sync ? [FireTriggerEvent::class, PollTrigger::class] : [FireTriggerEvent::class]);
    datadogFake($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'datadog') ?? Connector::factory()->create(['key' => 'datadog']))
        ->create(['data' => datadogData(), 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$config]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();

    if ($sync) {
        SyncTriggerSubscription::dispatchSync($trigger->id);
    }

    return $trigger->fresh();
}

describe('datadog nodes', function () {
    it('seeds the connector and the category, asking for both keys and the site', function () {
        $this->seed([ConnectorSeeder::class, AppNodeCategorySeeder::class]);

        $connector = Connector::query()->where('key', 'datadog')->firstOrFail();

        expect($connector->auth_type)->toBe(ConnectorAuthType::ApiKey)
            ->and(array_column($connector->fields, 'name'))->toBe(['api_key', 'app_key', 'site'])
            ->and(NodeCategory::query()->where('slug', 'datadog')->where('kind', 'app')->value('sort_order'))->toBe(62);
    });

    it('runs every node against a real endpoint on the connected site, with both keys', function () {
        $registry = app(NodeRegistry::class);
        $types = array_values(array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'datadog_')));
        $run = datadogRun(['site' => 'eu1']);

        expect(count($types))->toBeGreaterThanOrEqual(145);

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            $schema = $node->configSchema();

            expect($node)->toBeInstanceOf(AbstractDatadogNode::class)
                ->and($node->category())->toBe('datadog')
                ->and(array_diff($schema['required'], array_keys($schema['properties'])))->toBe([], "{$type} requires a field it does not have");

            Http::swap(new Factory);
            Http::fake(['*' => Http::response(['data' => [['id' => 'x', 'attributes' => []]], 'monitors' => [['id' => 1, 'status' => 'Alert']]])]);

            // These two read Query as JSON; everywhere else it is search text.
            $settings = in_array($type, ['datadog_create_slo', 'datadog_update_slo', 'datadog_api_request'], true) ? [...datadogEverySetting(), 'query' => ['numerator' => 'a', 'denominator' => 'b']] : datadogEverySetting();

            // A webhook's name sits in its path.
            $settings = str_contains($type, '_webhook') ? [...$settings, 'name' => 'my-hook'] : $settings;

            expect($node->execute($run, $settings, []))->toBeArray();

            foreach (Http::recorded() as [$request]) {
                expect((string) parse_url($request->url(), PHP_URL_HOST))->toEndWith('.datadoghq.eu')
                    ->and($request->hasHeader('DD-API-KEY', 'dd-api'))->toBeTrue()
                    ->and($request->hasHeader('DD-APPLICATION-KEY', 'dd-app'))->toBeTrue();
            }

            expect(count(Http::recorded()))->toBeGreaterThan(0);
        }
    });

    it('only ever sends the keys to a Datadog site', function () {
        Http::fake();
        $run = datadogRun(['site' => 'evil.example.com']);

        expect(fn () => datadogExec('datadog_list_monitors', [], $run))->toThrow(InvalidArgumentException::class, 'must be one of');
        Http::assertNothingSent();

        expect(AbstractDatadogNode::siteHost(''))->toBe('datadoghq.com')
            ->and(AbstractDatadogNode::siteHost('eu1'))->toBe('datadoghq.eu')
            ->and(AbstractDatadogNode::siteHost('https://app.datadoghq.eu/'))->toBe('datadoghq.eu')
            ->and(AbstractDatadogNode::siteHost('GOV'))->toBe('ddog-gov.com')
            ->and(fn () => AbstractDatadogNode::siteHost('datadoghq.com.evil.io'))->toThrow(InvalidArgumentException::class);
    });

    it('needs both keys', function () {
        Http::fake();

        expect(fn () => datadogExec('datadog_list_monitors', [], datadogRun(['app_key' => ''])))->toThrow(InvalidArgumentException::class, 'application key');
    });

    it('creates a monitor, and updates only what is filled in', function () {
        datadogFake(['POST /api/v1/monitor' => ['id' => 9, 'name' => 'CPU', 'overall_state' => 'OK', 'tags' => ['a:b']], 'PUT /api/v1/monitor/9' => ['id' => 9, 'name' => 'New']]);

        $created = datadogExec('datadog_create_monitor', ['name' => 'CPU', 'type' => 'metric alert', 'query' => 'avg(last_5m):x > 1', 'tags' => ['a:b'], 'priority' => 2]);

        expect($created)->toMatchArray(['id' => 9, 'status' => 'OK', 'tags' => ['a:b']])
            ->and(datadogBody('POST /api/v1/monitor'))->toBe(['name' => 'CPU', 'type' => 'metric alert', 'query' => 'avg(last_5m):x > 1', 'message' => '', 'tags' => ['a:b'], 'priority' => 2]);

        datadogExec('datadog_update_monitor', ['monitor_id' => '9', 'name' => 'New']);
        expect(datadogBody('PUT /api/v1/monitor/9'))->toBe(['name' => 'New'])
            ->and(fn () => datadogExec('datadog_update_monitor', ['monitor_id' => '9']))->toThrow(InvalidArgumentException::class, 'something to change');
    });

    it('mutes a monitor until a time, and unmutes every scope', function () {
        datadogFake(['POST /api/v1/monitor/9/*' => ['id' => 9]]);

        datadogExec('datadog_mute_monitor', ['monitor_id' => '9', 'scope' => 'host:a', 'end' => '2026-01-01T00:00:00Z']);
        expect(datadogQuery('POST /api/v1/monitor/9/mute'))->toBe(['scope' => 'host:a', 'end' => '1767225600']);

        datadogExec('datadog_unmute_monitor', ['monitor_id' => '9', 'all_scopes' => true]);
        expect(datadogQuery('POST /api/v1/monitor/9/unmute'))->toBe(['all_scopes' => 'true']);
    });

    it('says a missing record is not found, and refuses ids that are not safe in a path', function () {
        datadogFake();

        expect(datadogExec('datadog_get_monitor', ['monitor_id' => '404']))->toBe(['found' => false])
            ->and(datadogExec('datadog_get_dashboard', ['dashboard_id' => 'abc-def-ghi']))->toBe(['found' => false])
            ->and(fn () => datadogExec('datadog_get_monitor', ['monitor_id' => '1/../2']))->toThrow(InvalidArgumentException::class, 'must be a number')
            ->and(fn () => datadogExec('datadog_get_dashboard', ['dashboard_id' => '../org']))->toThrow(InvalidArgumentException::class)
            ->and(fn () => datadogExec('datadog_mute_host', ['host' => 'a?b=c']))->toThrow(InvalidArgumentException::class);
    });

    it('explains errors with a hint', function () {
        datadogFake([
            'GET /api/v1/monitor/1' => fn () => Http::response(['errors' => ['Forbidden']], 403),
            'GET /api/v1/monitor/2' => fn () => Http::response(['errors' => ['Rate limit exceeded']], 429),
            'GET /api/v1/monitor/3' => fn () => Http::response(['errors' => [['title' => 'Bad', 'detail' => 'Nope']]], 401),
        ]);

        expect(fn () => datadogExec('datadog_get_monitor', ['monitor_id' => '1']))->toThrow(RuntimeException::class, 'application key lacks the permission')
            ->and(fn () => datadogExec('datadog_get_monitor', ['monitor_id' => '2']))->toThrow(RuntimeException::class, 'rate limiting')
            ->and(fn () => datadogExec('datadog_get_monitor', ['monitor_id' => '3']))->toThrow(RuntimeException::class, 'Nope. Reconnect Datadog');
    });

    it('posts events and logs to their own intake hosts', function () {
        datadogFake(['POST /api/v2/events' => ['data' => ['id' => 'e1']], 'POST /api/v2/logs' => []]);

        datadogExec('datadog_create_event', ['title' => 'Deploy', 'category' => 'change', 'changed_resource' => 'flag-a', 'tags' => ['env:prod']]);
        expect(datadogRequest('POST /api/v2/events')->url())->toStartWith('https://event-management-intake.datadoghq.com/')
            ->and(datadogBody('POST /api/v2/events')['data']['attributes'])->toBe(['title' => 'Deploy', 'category' => 'change', 'tags' => ['env:prod'], 'attributes' => ['changed_resource' => ['name' => 'flag-a', 'type' => 'configuration']]]);

        datadogExec('datadog_send_logs', ['message' => 'Hi', 'service' => 'web', 'tags' => ['a:b', 'c:d']]);
        expect(datadogRequest('POST /api/v2/logs')->url())->toStartWith('https://http-intake.logs.datadoghq.com/')
            ->and(datadogBody('POST /api/v2/logs'))->toBe([['message' => 'Hi', 'service' => 'web', 'ddtags' => 'a:b,c:d']]);
    });

    it('submits a metric point and queries points back', function () {
        datadogFake(['POST /api/v2/series' => ['errors' => []], 'GET /api/v1/query' => ['series' => [['metric' => 'm']], 'status' => 'ok']]);

        expect(datadogExec('datadog_submit_metric', ['metric' => 'my.count', 'value' => '3', 'type' => 'count', 'timestamp' => '100', 'host' => 'web-1'])['accepted'])->toBeTrue()
            ->and(datadogBody('POST /api/v2/series')['series'][0])->toBe(['metric' => 'my.count', 'type' => 1, 'points' => [['timestamp' => 100, 'value' => 3]], 'resources' => [['name' => 'web-1', 'type' => 'host']]])
            ->and(fn () => datadogExec('datadog_submit_metric', ['metric' => 'x', 'value' => 'abc']))->toThrow(InvalidArgumentException::class, 'must be a number');

        expect(datadogExec('datadog_query_metrics', ['query' => 'avg:x{*}', 'from' => '100', 'to' => '200'])['count'])->toBe(1)
            ->and(datadogQuery('GET /api/v1/query'))->toBe(['query' => 'avg:x{*}', 'from' => '100', 'to' => '200']);
    });

    it('searches logs and spans with the shared filter, and flattens results', function () {
        datadogFake([
            'POST /api/v2/logs/events/search' => ['data' => [['id' => 'l1', 'attributes' => ['message' => 'boom', 'status' => 'error']]], 'meta' => ['page' => ['after' => 'next']]],
            'POST /api/v2/spans/events/search' => ['data' => [['id' => 's1', 'type' => 'spans', 'attributes' => ['service' => 'web']]]],
        ]);

        expect(datadogExec('datadog_search_logs', ['query' => 'status:error', 'limit' => 10]))->toMatchArray(['count' => 1, 'next_cursor' => 'next'])
            ->and(datadogBody('POST /api/v2/logs/events/search'))->toBe(['filter' => ['from' => 'now-15m', 'to' => 'now', 'query' => 'status:error'], 'page' => ['limit' => 10], 'sort' => '-timestamp']);

        expect(datadogExec('datadog_search_spans', ['query' => 'service:web'])['spans'][0])->toBe(['id' => 's1', 'type' => 'spans', 'service' => 'web'])
            ->and(datadogBody('POST /api/v2/spans/events/search')['data']['type'])->toBe('search_request');
    });

    it('declares an incident with its state and severity', function () {
        datadogFake(['POST /api/v2/incidents' => ['data' => ['id' => 'i1', 'type' => 'incidents', 'attributes' => ['title' => 'Down', 'fields' => ['state' => ['value' => 'active'], 'severity' => ['value' => 'SEV-1']]]]]]);

        expect(datadogExec('datadog_create_incident', ['title' => 'Down', 'severity' => 'SEV-1', 'state' => 'active', 'customer_impacted' => false]))->toMatchArray(['id' => 'i1', 'state' => 'active', 'severity' => 'SEV-1'])
            ->and(datadogBody('POST /api/v2/incidents')['data']['attributes'])->toMatchArray(['title' => 'Down', 'customer_impacted' => false, 'fields' => ['state' => ['type' => 'dropdown', 'value' => 'active'], 'severity' => ['type' => 'dropdown', 'value' => 'SEV-1']]]);
    });

    it('needs a monitor or tags to schedule a downtime', function () {
        datadogFake(['POST /api/v2/downtime' => ['data' => ['id' => 'd1', 'attributes' => ['scope' => '*']]]]);

        expect(fn () => datadogExec('datadog_create_downtime', ['scope' => '*']))->toThrow(InvalidArgumentException::class, 'monitor')
            ->and(datadogExec('datadog_create_downtime', ['scope' => '*', 'monitor_tags' => ['a:b'], 'end' => '2026-01-01T00:00:00Z'])['id'])->toBe('d1')
            ->and(datadogBody('POST /api/v2/downtime')['data']['attributes'])->toBe(['scope' => '*', 'monitor_identifier' => ['monitor_tags' => ['a:b']], 'schedule' => ['end' => '2026-01-01T00:00:00Z']]);
    });

    it('keeps the rest of a dashboard, SLO, test and notebook when changing one setting', function () {
        datadogFake([
            'GET /api/v1/dashboard/a-b-c' => ['id' => 'a-b-c', 'title' => 'Old', 'layout_type' => 'ordered', 'widgets' => [['id' => 1]], 'author_handle' => 'x'],
            'PUT /api/v1/dashboard/a-b-c' => ['id' => 'a-b-c'],
            'GET /api/v1/slo/s1' => ['data' => ['id' => 's1', 'name' => 'Old', 'type' => 'metric', 'thresholds' => [['target' => 99]], 'creator' => ['x']]],
            'PUT /api/v1/slo/s1' => ['data' => [['id' => 's1']]],
            'GET /api/v1/synthetics/tests/api/a-b-c' => ['public_id' => 'a-b-c', 'name' => 'Old', 'type' => 'api', 'config' => ['a' => 1], 'locations' => ['x'], 'options' => ['b' => 1], 'message' => 'm', 'monitor_id' => 4],
            'PUT /api/v1/synthetics/tests/api/a-b-c' => ['public_id' => 'a-b-c'],
            'GET /api/v1/notebooks/7' => ['data' => ['id' => 7, 'attributes' => ['name' => 'Old', 'cells' => [['x']], 'time' => ['live_span' => '1h'], 'author' => ['y']]]],
            'PUT /api/v1/notebooks/7' => ['data' => ['id' => 7, 'attributes' => ['name' => 'New']]],
        ]);

        datadogExec('datadog_update_dashboard', ['dashboard_id' => 'a-b-c', 'title' => 'New']);
        expect(datadogBody('PUT /api/v1/dashboard/a-b-c'))->toBe(['title' => 'New', 'layout_type' => 'ordered', 'widgets' => [['id' => 1]]]);

        expect(datadogExec('datadog_update_slo', ['slo_id' => 's1', 'name' => 'New'])['id'])->toBe('s1')
            ->and(datadogBody('PUT /api/v1/slo/s1'))->toBe(['name' => 'New', 'type' => 'metric', 'thresholds' => [['target' => 99]]]);

        datadogExec('datadog_update_api_synthetic', ['public_id' => 'a-b-c', 'name' => 'New']);
        expect(datadogBody('PUT /api/v1/synthetics/tests/api/a-b-c'))->toBe(['name' => 'New', 'type' => 'api', 'config' => ['a' => 1], 'locations' => ['x'], 'options' => ['b' => 1], 'message' => 'm']);

        datadogExec('datadog_update_notebook', ['notebook_id' => '7', 'name' => 'New']);
        expect(datadogBody('PUT /api/v1/notebooks/7')['data']['attributes'])->toBe(['name' => 'New', 'cells' => [['x']], 'time' => ['live_span' => '1h']]);
    });

    it('runs, pauses and deletes synthetic tests', function () {
        datadogFake([
            'POST /api/v1/synthetics/tests/trigger' => ['batch_id' => 'b', 'results' => [['result_id' => 'r']]],
            'PUT /api/v1/synthetics/tests/a-b-c/status' => fn () => Http::response('true'),
            'POST /api/v1/synthetics/tests/delete' => ['deleted_tests' => [['public_id' => 'a-b-c']]],
        ]);

        expect(datadogExec('datadog_trigger_synthetics', ['public_ids' => ['a-b-c']])['batch_id'])->toBe('b')
            ->and(datadogBody('POST /api/v1/synthetics/tests/trigger'))->toBe(['tests' => [['public_id' => 'a-b-c']]])
            ->and(datadogExec('datadog_set_synthetic_status', ['public_id' => 'a-b-c', 'status' => 'paused']))->toBe(['id' => 'a-b-c', 'status' => 'paused'])
            ->and(datadogBody('PUT /api/v1/synthetics/tests/a-b-c/status'))->toBe(['new_status' => 'paused'])
            ->and(datadogExec('datadog_delete_synthetics', ['public_ids' => ['a-b-c']])['deleted'])->toHaveCount(1);
    });

    it('triages security signals with the right body', function () {
        datadogFake(['PATCH /api/v2/security_monitoring/signals/s1/*' => ['data' => ['id' => 's1', 'attributes' => ['state' => 'archived']]]]);

        datadogExec('datadog_update_signal_state', ['signal_id' => 's1', 'state' => 'archived', 'archive_reason' => 'false_positive']);
        expect(datadogBody('PATCH /api/v2/security_monitoring/signals/s1/state'))->toBe(['data' => ['type' => 'signal_metadata', 'attributes' => ['state' => 'archived', 'archive_reason' => 'false_positive']]]);

        datadogExec('datadog_assign_signal', ['signal_id' => 's1', 'user_id' => 'uuid-1']);
        expect(datadogBody('PATCH /api/v2/security_monitoring/signals/s1/assignee')['data']['attributes']['assignee']['uuid'])->toBe('uuid-1');
    });

    it('calls any API path on the site, and only there', function () {
        datadogFake(['GET /api/v1/org' => ['org' => ['name' => 'Acme']]]);

        expect(datadogExec('datadog_api_request', ['method' => 'GET', 'path' => '/api/v1/org', 'query' => ['a' => 'b']]))->toBe(['status' => 200, 'body' => ['org' => ['name' => 'Acme']]])
            ->and(fn () => datadogExec('datadog_api_request', ['method' => 'GET', 'path' => 'https://evil.io/api/v1/org']))->toThrow(InvalidArgumentException::class)
            ->and(fn () => datadogExec('datadog_api_request', ['method' => 'GET', 'path' => '/api/v1/../../x']))->toThrow(InvalidArgumentException::class);
    });
});

describe('datadog options', function () {
    it('lists monitors, dashboards, SLOs, tests, users and teams', function () {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $query = fn (?string $search = null): NodeOptionsQuery => new NodeOptionsQuery($workspace, $owner, [], 'dd-api', $search, credentialData: datadogData());

        datadogFake([
            'GET /api/v1/monitor/search' => ['monitors' => [['id' => 9, 'name' => 'CPU', 'status' => 'Alert']]],
            'GET /api/v1/dashboard' => ['dashboards' => [['id' => 'a-b-c', 'title' => 'Web'], ['id' => 'd-e-f', 'title' => 'Db']]],
            'GET /api/v1/slo' => ['data' => [['id' => 's1', 'name' => 'Uptime', 'type' => 'metric']]],
            'GET /api/v1/synthetics/tests/search' => ['tests' => [['public_id' => 'x-y-z', 'name' => 'Home', 'type' => 'api']]],
            'GET /api/v2/users' => ['data' => [['id' => 'u1', 'attributes' => ['name' => 'Ada', 'email' => 'ada@example.com']]]],
            'GET /api/v2/team' => ['data' => [['id' => 't1', 'attributes' => ['name' => 'Payments', 'handle' => 'payments']]]],
        ]);

        $options = new DatadogOptions;

        expect($options->sources())->toHaveCount(6)
            ->and($options->load('datadog.monitors', $query())->options[0]->toArray())->toMatchArray(['value' => '9', 'label' => 'CPU', 'description' => 'Alert'])
            ->and($options->load('datadog.dashboards', $query('web'))->options[0]->value)->toBe('a-b-c')
            ->and($options->load('datadog.slos', $query())->options[0]->value)->toBe('s1')
            ->and($options->load('datadog.synthetics', $query())->options[0]->value)->toBe('x-y-z')
            ->and($options->load('datadog.users', $query())->options[0]->label)->toBe('Ada')
            ->and($options->load('datadog.teams', $query())->options[0]->label)->toBe('Payments');
    });

    it('turns a refusal into a short message', function () {
        datadogFake(['GET /api/v1/monitor/search' => fn () => Http::response(['errors' => ['no']], 403)]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

        (new DatadogOptions)->load('datadog.monitors', new NodeOptionsQuery($workspace, $owner, [], 'dd-api', credentialData: datadogData()));
    })->throws(ConnectorException::class, "doesn't have access");
});

describe('datadog connection check', function () {
    $account = function (array $data = []): ConnectorCredential {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

        return ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'datadog') ?? Connector::factory()->create(['key' => 'datadog', 'name' => 'Datadog']))
            ->create(['data' => datadogData($data), 'created_by' => $owner->id]);
    };

    it('validates both keys on the site', function () use ($account) {
        datadogFake(['GET /api/v2/validate_keys' => ['valid' => true]]);

        expect(app(ConnectorCredentialTester::class)->test($account(['site' => 'us5'])))->toMatchArray(['ok' => true, 'account' => 'us5.datadoghq.com']);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.us5.datadoghq.com/') && $request->hasHeader('DD-APPLICATION-KEY', 'dd-app'));
    });

    it('fails rejected keys, a missing application key and an unknown site', function () use ($account) {
        datadogFake(['GET /api/v2/validate_keys' => fn () => Http::response(['errors' => ['Forbidden']], 403)]);
        expect(app(ConnectorCredentialTester::class)->test($account())['ok'])->toBeFalse();

        datadogFake();
        expect(app(ConnectorCredentialTester::class)->test($account(['app_key' => '']))['message'])->toContain('application key')
            ->and(app(ConnectorCredentialTester::class)->test($account(['site' => 'evil.io']))['message'])->toContain('must be one of');
    });
});

describe('datadog triggers', function () {
    it('polls new events: a baseline first, then one run for each', function () {
        $trigger = datadogTrigger('datadog_list_events');
        $poll = fn (array $ids) => datadogFake(['GET /api/v2/events' => ['data' => array_map(fn (string $id): array => ['id' => $id, 'attributes' => ['message' => 'm', 'attributes' => ['title' => "Event {$id}"]]], $ids)]]);

        $poll(['e1']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['e3', 'e2', 'e1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['e2', 'e3'])
            ->and($trigger->events()->where('status', 'queued')->get()->pluck('payload.title')->sort()->values()->all())->toBe(['Event e2', 'Event e3']);
    });

    it('fires a state change whenever a monitor moves, even back to a state it had', function () {
        $trigger = datadogTrigger('datadog_list_monitor_state_changes');
        $poll = fn (string $status, int $triggered) => datadogFake(['GET /api/v1/monitor/search' => ['monitors' => [['id' => 9, 'name' => 'CPU', 'status' => $status, 'last_triggered_ts' => $triggered]]]]);

        $poll('OK', 100);
        dispatch_sync(new PollTrigger($trigger));
        $poll('Alert', 200);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        $poll('OK', 200);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        $poll('Alert', 300);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.change_id')->sort()->values()->all())->toBe(['9:Alert:200', '9:Alert:300', '9:OK:200']);
    });

    it('keeps only alerting monitors on the alert trigger', function () {
        datadogFake(['GET /api/v1/monitor/search' => ['monitors' => [['id' => 1, 'status' => 'OK'], ['id' => 2, 'status' => 'Alert'], ['id' => 3, 'status' => 'No Data'], ['id' => 4, 'status' => 'Ignored']]]]);

        expect(array_column(datadogExec('datadog_list_monitor_alerts')['monitors'], 'id'))->toBe([2, 3]);
    });

    it('reads one chosen monitor directly, with the times of its groups', function () {
        datadogFake(['GET /api/v1/monitor/9' => ['id' => 9, 'name' => 'CPU', 'overall_state' => 'Alert', 'state' => ['groups' => ['a' => ['last_triggered_ts' => 50, 'last_resolved_ts' => 10], 'b' => ['last_triggered_ts' => 70]]]]]);

        $monitor = datadogExec('datadog_list_monitor_state_changes', ['monitor_id' => '9'])['monitors'][0];

        expect($monitor)->toMatchArray(['id' => 9, 'status' => 'Alert', 'change_id' => '9:Alert:70:10', 'last_triggered_at' => gmdate('c', 70)]);
    });

    it('polls new incidents, newest first', function () {
        $trigger = datadogTrigger('datadog_list_new_incidents');
        $poll = fn (array $ids) => datadogFake(['GET /api/v2/incidents/search' => ['data' => ['attributes' => ['incidents' => array_map(fn (string $id): array => ['data' => ['id' => $id, 'type' => 'incidents', 'attributes' => ['title' => 'T', 'state' => 'active']]], $ids)]]]]);

        $poll(['i1']);
        dispatch_sync(new PollTrigger($trigger));
        $poll(['i2', 'i1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->all())->toBe(['i2'])
            ->and(datadogQuery('GET /api/v2/incidents/search'))->toMatchArray(['sort' => '-created', 'query' => 'state:(active OR stable OR resolved)', 'page[size]' => '100']);
    });

    it('polls logs and security signals, sending the signal search full dates', function () {
        $logs = datadogTrigger('datadog_search_logs', ['query' => 'status:error']);
        $poll = fn (array $ids) => datadogFake(['POST /api/v2/logs/events/search' => ['data' => array_map(fn (string $id): array => ['id' => $id, 'attributes' => ['message' => 'x']], $ids)]]);

        $poll(['l1']);
        dispatch_sync(new PollTrigger($logs));
        $poll(['l2', 'l1']);
        dispatch_sync(new PollTrigger($logs->fresh()));
        expect($logs->events()->where('status', 'queued')->get()->pluck('payload.id')->all())->toBe(['l2']);

        datadogFake(['POST /api/v2/security_monitoring/signals/search' => ['data' => [['id' => 'g1', 'attributes' => ['message' => 'bad', 'custom' => ['title' => 'Attack', 'status' => 'high']]]]]]);
        $signal = datadogExec('datadog_search_security_signals', ['query' => 'security:attack'])['signals'][0];

        expect($signal)->toMatchArray(['id' => 'g1', 'title' => 'Attack', 'severity' => 'high'])
            ->and(datadogBody('POST /api/v2/security_monitoring/signals/search')['filter']['from'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
    });
});

describe('datadog instant delivery', function () {
    $routes = [
        'POST /api/v1/integration/webhooks/configuration/webhooks' => ['name' => 'x'],
        'GET /api/v1/monitor/9' => ['id' => 9, 'message' => 'Hello @team'],
        'PUT /api/v1/monitor/9' => ['id' => 9],
    ];

    it('stays on polling unless asked for, and needs one monitor', function () {
        datadogTrigger('datadog_list_monitor_alerts', ['monitor_id' => '9'], sync: true);
        datadogTrigger('datadog_list_monitor_alerts', ['instant_delivery' => true], sync: true);

        expect(ProviderWatch::query()->count())->toBe(0);
        Http::assertNothingSent();
    });

    it('makes a webhook, mentions it in the monitor, and starts a poll when it is called', function () use ($routes) {
        $trigger = datadogTrigger('datadog_list_monitor_alerts', ['monitor_id' => '9', 'instant_delivery' => true], $routes, true);
        $watch = ProviderWatch::query()->sole();
        $webhook = datadogBody('POST /api/v1/integration/webhooks/configuration/webhooks');

        expect($watch->resource_key)->toBe('monitor:9')
            ->and($watch->external_id)->toBe($webhook['name'])
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($webhook['url'])->toBe(url('/api/hooks/apps/datadog').'?token='.$watch->secret)
            ->and(json_decode($webhook['payload'], true))->toHaveKeys(['id', 'alert_id', 'transition'])
            ->and(datadogBody('PUT /api/v1/monitor/9'))->toBe(['message' => "Hello @team\n@webhook-{$watch->external_id}"]);

        $body = ['id' => '555', 'alert_id' => '9', 'transition' => 'Triggered', 'title' => 'CPU'];
        test()->postJson('/api/hooks/apps/datadog?token='.$watch->secret, $body)->assertOk();
        test()->postJson('/api/hooks/apps/datadog?token='.$watch->secret, $body)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('refuses a wrong token and is closed while the switch is off', function () use ($routes) {
        datadogTrigger('datadog_list_monitor_alerts', ['monitor_id' => '9', 'instant_delivery' => true], $routes, true);
        $watch = ProviderWatch::query()->sole();
        $body = ['id' => '555', 'alert_id' => '9', 'transition' => 'Triggered'];

        test()->postJson('/api/hooks/apps/datadog?token=nope', $body)->assertUnauthorized();
        config(['triggers.push.datadog.enabled' => false]);
        test()->postJson('/api/hooks/apps/datadog?token='.$watch->secret, $body)->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('stays on polling when Datadog refuses the webhook', function () {
        $trigger = datadogTrigger('datadog_list_monitor_alerts', ['monitor_id' => '9', 'instant_delivery' => true], ['POST /api/v1/integration/webhooks/configuration/webhooks' => fn () => Http::response(['errors' => ['Forbidden']], 403)], true);

        expect($trigger->subscription->status)->not->toBe(SubscriptionStatus::Active)->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('puts back a mention that was edited out, remakes a lost webhook, and cleans up after itself', function () use ($routes) {
        datadogTrigger('datadog_list_monitor_alerts', ['monitor_id' => '9', 'instant_delivery' => true], $routes, true);
        $watch = ProviderWatch::query()->sole();
        $name = $watch->external_id;
        $source = app(DatadogSource::class);
        $webhook = '/api/v1/integration/webhooks/configuration/webhooks';

        datadogFake(["GET {$webhook}/{$name}" => ['name' => $name], 'GET /api/v1/monitor/9' => ['message' => 'Edited'], 'PUT /api/v1/monitor/9' => []]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect(datadogBody('PUT /api/v1/monitor/9'))->toBe(['message' => "Edited\n@webhook-{$name}"]);

        datadogFake(["GET {$webhook}/{$name}" => fn () => Http::response(['errors' => ['gone']], 404), "PUT {$webhook}/{$name}" => fn () => Http::response(['errors' => ['gone']], 404), "POST {$webhook}" => ['name' => $name], 'GET /api/v1/monitor/9' => ['message' => "Hi\n@webhook-{$name}"]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect(datadogBody("POST {$webhook}")['name'])->toBe($name);

        datadogFake(['GET /api/v1/monitor/9' => ['message' => "Hi\n@webhook-{$name}"], 'PUT /api/v1/monitor/9' => [], "DELETE {$webhook}/{$name}" => []]);
        app(WatchManager::class)->remove($source, $watch->fresh());

        expect(datadogBody('PUT /api/v1/monitor/9'))->toBe(['message' => 'Hi'])
            ->and(ProviderWatch::query()->count())->toBe(0);
    });
});
