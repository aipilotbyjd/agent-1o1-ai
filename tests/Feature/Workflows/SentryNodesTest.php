<?php

use App\Enums\Connectors\ConnectorAuthType;
use App\Exceptions\ConnectorException;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Nodes\NodeCategory;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Sentry\AbstractSentryNode;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Http\SsrfGuard;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\SentryOptions;
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
function sentryData(array $data = []): array
{
    return ['api_key' => 'sntrys_token', 'organization' => 'acme', ...$data];
}

/**
 * A run with a connected Sentry account in its workspace.
 *
 * @param  array<string, mixed>  $data
 */
function sentryRun(array $data = []): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'sentry') ?? Connector::factory()->create(['key' => 'sentry']))->id,
        'data' => sentryData($data),
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Sentry. A key is "METHOD /path" under /api/0; the value is the JSON body, or
 * a closure returning a response. A route nobody answers is a 404, so a node that
 * calls something unexpected fails.
 *
 * @param  array<string, mixed>  $routes
 */
function sentryFake(array $routes = []): void
{
    Http::swap(new Factory);
    Http::fake(['*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.preg_replace('~^/api/0~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['detail' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function sentryRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/api/0~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<mixed>
 */
function sentryBody(string $route): array
{
    return (array) json_decode(sentryRequest($route)->body(), true);
}

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function sentryExec(string $type, array $config = [], ?Run $run = null): array
{
    return app(NodeRegistry::class)->resolve($type)->execute($run ?? sentryRun(), $config, []);
}

/**
 * Every setting any node needs, valid for all of them.
 *
 * @return array<string, mixed>
 */
function sentryEverySetting(): array
{
    return [
        'project' => 'backend', 'team' => 'engineering', 'member_id' => '42', 'issue_id' => '1234', 'issue_ids' => ['1234', '1235'], 'event_id' => 'abcdef0123', 'short_id' => 'BACKEND-1A',
        'version' => 'backend@1.2.3', 'projects' => ['backend'], 'environment' => 'production', 'name' => 'A name', 'title' => 'A title', 'email' => 'ada@example.com', 'role' => 'member',
        'key_id' => 'abc123', 'hook_id' => 'hook1', 'url' => 'https://example.com/hook', 'events' => ['event.created'], 'key' => 'browser', 'text' => 'A comment', 'comment_id' => '9', 'status' => 'resolved',
        'fields' => ['title', 'count()'], 'trace_id' => 'abc123', 'alert_id' => '7', 'monitor_id' => '8', 'type' => 'metric_issue', 'cron' => 'nightly-job', 'cron_project' => 'backend',
        'config' => ['schedule_type' => 'crontab', 'schedule' => '0 2 * * *'], 'dashboard_id' => '11', 'query_id' => '12', 'repository_id' => '13', 'path' => '/api/0/organizations/', 'method' => 'GET',
    ];
}

/**
 * A published trigger on a Sentry reader.
 *
 * @param  array<string, mixed>  $config
 */
function sentryTrigger(string $nodeType, array $config = []): Trigger
{
    Queue::fake([FireTriggerEvent::class]);
    sentryFake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'sentry') ?? Connector::factory()->create(['key' => 'sentry']))
        ->create(['data' => sentryData(), 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$config]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    return Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail()->fresh();
}

describe('sentry nodes', function () {
    it('seeds the connector and the category, asking for the token, organization and address', function () {
        $this->seed([ConnectorSeeder::class, AppNodeCategorySeeder::class]);

        $connector = Connector::query()->where('key', 'sentry')->firstOrFail();

        expect($connector->auth_type)->toBe(ConnectorAuthType::ApiKey)
            ->and(array_column($connector->fields, 'name'))->toBe(['api_key', 'organization', 'base_url'])
            ->and(NodeCategory::query()->where('slug', 'sentry')->where('kind', 'app')->value('sort_order'))->toBe(63);
    });

    it('runs every node against sentry.io with the token', function () {
        $registry = app(NodeRegistry::class);
        $types = array_values(array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'sentry_')));
        $run = sentryRun();

        expect(count($types))->toBeGreaterThanOrEqual(113);

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            $schema = $node->configSchema();

            expect($node)->toBeInstanceOf(AbstractSentryNode::class)
                ->and($node->category())->toBe('sentry')
                ->and(array_diff($schema['required'], array_keys($schema['properties'])))->toBe([], "{$type} requires a field it does not have");

            Http::swap(new Factory);
            Http::fake(['*' => Http::response([['id' => '1', 'title' => 't', 'version' => 'v']])]);

            expect($node->execute($run, sentryEverySetting(), []))->toBeArray();

            foreach (Http::recorded() as [$request]) {
                expect($request->url())->toStartWith('https://sentry.io/api/0/')
                    ->and($request->hasHeader('Authorization', 'Bearer sntrys_token'))->toBeTrue();
            }

            expect(count(Http::recorded()))->toBeGreaterThan(0);
        }
    });

    it('uses the node\'s organization, else the connection\'s, and refuses a bad slug', function () {
        sentryFake(['GET /organizations/*/projects/' => [['id' => '1', 'slug' => 'web']]]);

        sentryExec('sentry_list_projects');
        expect(sentryRequest('GET /organizations/acme/projects/')->url())->toContain('per_page=50');

        Http::swap(new Factory);
        sentryFake(['GET /organizations/*/projects/' => [['id' => '1', 'slug' => 'web']]]);
        sentryExec('sentry_list_projects', ['organization' => 'other']);
        sentryRequest('GET /organizations/other/projects/');

        expect(fn () => sentryExec('sentry_list_projects', ['organization' => '../evil']))->toThrow(InvalidArgumentException::class, 'must be a slug')
            ->and(fn () => sentryExec('sentry_list_projects', [], sentryRun(['organization' => ''])))->toThrow(InvalidArgumentException::class, 'organization is required');
    });

    it('reads the next page from the Link header', function () {
        sentryFake(['GET /organizations/acme/releases/' => fn () => Http::response([['version' => 'v1', 'projects' => [['slug' => 'web']]]], 200, [
            'Link' => '<https://sentry.io/x?cursor=a>; rel="previous"; results="false"; cursor="a", <https://sentry.io/x?cursor=b>; rel="next"; results="true"; cursor="b:0:1"',
        ])]);

        expect(sentryExec('sentry_list_releases'))->toMatchArray(['count' => 1, 'next_cursor' => 'b:0:1'])
            ->and(sentryExec('sentry_list_releases')['releases'][0])->toMatchArray(['version' => 'v1', 'projects' => ['web']]);
    });

    it('stops at the last page', function () {
        sentryFake(['GET /organizations/acme/releases/' => fn () => Http::response([], 200, ['Link' => '<https://sentry.io/x>; rel="next"; results="false"; cursor="b"'])]);

        expect(sentryExec('sentry_list_releases')['next_cursor'])->toBeNull();
    });

    it('updates, resolves, archives, assigns and deletes an issue', function () {
        sentryFake(['PUT /organizations/acme/issues/1234/' => ['id' => '1234', 'shortId' => 'B-1', 'status' => 'resolved', 'project' => ['slug' => 'web']], 'DELETE /organizations/acme/issues/1234/' => []]);

        expect(sentryExec('sentry_update_issue', ['issue_id' => '1234', 'status' => 'resolved', 'priority' => 'high', 'is_bookmarked' => true]))->toMatchArray(['id' => '1234', 'short_id' => 'B-1', 'project' => 'web'])
            ->and(sentryBody('PUT /organizations/acme/issues/1234/'))->toBe(['status' => 'resolved', 'priority' => 'high', 'isBookmarked' => true])
            ->and(fn () => sentryExec('sentry_update_issue', ['issue_id' => '1234']))->toThrow(InvalidArgumentException::class, 'something to change');

        foreach ([
            [['issue_id' => '1234'], 'sentry_resolve_issue', ['status' => 'resolved']],
            [['issue_id' => '1234', 'in_next_release' => true], 'sentry_resolve_issue', ['status' => 'resolvedInNextRelease']],
            [['issue_id' => '1234'], 'sentry_unresolve_issue', ['status' => 'unresolved']],
            [['issue_id' => '1234', 'until' => 'archived_forever'], 'sentry_ignore_issue', ['status' => 'ignored', 'substatus' => 'archived_forever']],
            [['issue_id' => '1234', 'minutes' => 60], 'sentry_ignore_issue', ['status' => 'ignored', 'substatus' => 'archived_until_condition_met', 'statusDetails' => ['ignoreDuration' => 60]]],
            [['issue_id' => '1234', 'assigned_to' => 'ada@example.com'], 'sentry_assign_issue', ['assignedTo' => 'ada@example.com']],
            [['issue_id' => '1234'], 'sentry_assign_issue', ['assignedTo' => '']],
        ] as [$config, $type, $expected]) {
            sentryFake(['PUT /organizations/acme/issues/1234/' => ['id' => '1234']]);
            sentryExec($type, $config);
            expect(sentryBody('PUT /organizations/acme/issues/1234/'))->toBe($expected);
        }

        sentryFake(['DELETE /organizations/acme/issues/1234/' => []]);
        expect(sentryExec('sentry_delete_issue', ['issue_id' => '1234']))->toBe(['deleted' => true, 'id' => '1234']);
    });

    it('sends bulk ids once each, and only numbers', function () {
        sentryFake(['PUT /organizations/acme/issues/' => ['status' => 'resolved']]);

        sentryExec('sentry_bulk_update_issues', ['issue_ids' => ['1', '2'], 'status' => 'resolved']);
        expect(parse_url(sentryRequest('PUT /organizations/acme/issues/')->url(), PHP_URL_QUERY))->toBe('id=1&id=2')
            ->and(fn () => sentryExec('sentry_bulk_update_issues', ['issue_ids' => ['1&id=2'], 'status' => 'resolved']))->toThrow(InvalidArgumentException::class, 'must be numbers');
    });

    it('searches issues, looking a project slug up to its id', function () {
        sentryFake(['GET /projects/acme/web/' => ['id' => '77'], 'GET /organizations/acme/issues/' => [['id' => '1', 'title' => 'Boom', 'assignedTo' => ['email' => 'ada@example.com']]]]);

        $output = sentryExec('sentry_list_issues', ['project' => 'web', 'query' => 'is:unresolved level:error', 'sort' => 'freq', 'limit' => 5]);

        expect($output['issues'][0])->toMatchArray(['id' => '1', 'title' => 'Boom', 'assigned_to' => 'ada@example.com']);
        parse_str((string) parse_url(sentryRequest('GET /organizations/acme/issues/')->url(), PHP_URL_QUERY), $query);
        expect($query)->toMatchArray(['project' => '77', 'query' => 'is:unresolved level:error', 'sort' => 'freq', 'limit' => '5']);
    });

    it('says a missing record is not found, and refuses ids that are not safe in a path', function () {
        sentryFake();

        expect(sentryExec('sentry_get_issue', ['issue_id' => '1']))->toBe(['found' => false])
            ->and(sentryExec('sentry_get_project', ['project' => 'web']))->toBe(['found' => false])
            ->and(fn () => sentryExec('sentry_get_issue', ['issue_id' => '1/../2']))->toThrow(InvalidArgumentException::class)
            ->and(fn () => sentryExec('sentry_get_project', ['project' => 'a?b']))->toThrow(InvalidArgumentException::class)
            ->and(fn () => sentryExec('sentry_list_tag_values', ['project' => 'web', 'key' => '../x']))->toThrow(InvalidArgumentException::class);
    });

    it('encodes a release version in the path', function () {
        sentryFake(['GET /organizations/acme/releases/*' => ['version' => 'a/b@1.0+x']]);

        expect(sentryExec('sentry_get_release', ['version' => 'a/b@1.0+x'])['version'])->toBe('a/b@1.0+x');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/releases/a%2Fb%401.0%2Bx/'));

        expect(fn () => sentryExec('sentry_get_release', ['version' => '..']))->toThrow(InvalidArgumentException::class);
    });

    it('creates a release and records a deploy', function () {
        sentryFake(['POST /organizations/acme/releases/' => ['version' => 'v1'], 'POST /organizations/acme/releases/v1/deploys/' => ['id' => '5', 'environment' => 'production']]);

        sentryExec('sentry_create_release', ['version' => 'v1', 'projects' => ['web'], 'ref' => 'abc']);
        expect(sentryBody('POST /organizations/acme/releases/'))->toBe(['version' => 'v1', 'projects' => ['web'], 'ref' => 'abc'])
            ->and(fn () => sentryExec('sentry_create_release', ['version' => 'v1']))->toThrow(InvalidArgumentException::class, 'project');

        expect(sentryExec('sentry_create_deploy', ['version' => 'v1', 'environment' => 'production', 'name' => 'd1']))->toMatchArray(['id' => '5'])
            ->and(sentryBody('POST /organizations/acme/releases/v1/deploys/'))->toBe(['environment' => 'production', 'name' => 'd1']);
    });

    it('creates a project under a team, or under the organization', function () {
        sentryFake(['POST /teams/acme/eng/projects/' => ['id' => '1', 'slug' => 'web', 'team' => ['slug' => 'eng']], 'POST /organizations/acme/projects/' => ['id' => '2', 'slug' => 'api']]);

        expect(sentryExec('sentry_create_project', ['team' => 'eng', 'name' => 'Web', 'platform' => 'php'])['team'])->toBe('eng')
            ->and(sentryBody('POST /teams/acme/eng/projects/'))->toBe(['name' => 'Web', 'platform' => 'php'])
            ->and(sentryExec('sentry_create_project', ['name' => 'Api'])['slug'])->toBe('api');
    });

    it('invites a member and adds one to a team', function () {
        sentryFake(['POST /organizations/acme/members/' => ['id' => '42', 'email' => 'ada@example.com', 'orgRole' => 'member'], 'POST /organizations/acme/members/42/teams/eng/' => ['id' => '1', 'slug' => 'eng']]);

        expect(sentryExec('sentry_add_member', ['email' => 'ada@example.com', 'role' => 'member', 'teams' => ['eng']]))->toMatchArray(['id' => '42', 'role' => 'member'])
            ->and(sentryBody('POST /organizations/acme/members/'))->toBe(['email' => 'ada@example.com', 'orgRole' => 'member', 'teamRoles' => [['teamSlug' => 'eng', 'role' => null]]])
            ->and(fn () => sentryExec('sentry_add_member', ['email' => 'nope']))->toThrow(InvalidArgumentException::class, 'email')
            ->and(sentryExec('sentry_add_member_to_team', ['member_id' => '42', 'team' => 'eng'])['slug'])->toBe('eng');
    });

    it('comments on an issue through its notes', function () {
        sentryFake(['POST /organizations/acme/issues/1/notes/' => ['id' => '9', 'data' => ['text' => 'Hi'], 'dateCreated' => '2026-01-01'], 'GET /organizations/acme/issues/1/notes/' => [['id' => '9', 'data' => ['text' => 'Hi'], 'user' => ['email' => 'a@b.co']]]]);

        expect(sentryExec('sentry_add_issue_comment', ['issue_id' => '1', 'text' => 'Hi']))->toMatchArray(['id' => '9', 'text' => 'Hi'])
            ->and(sentryBody('POST /organizations/acme/issues/1/notes/'))->toBe(['text' => 'Hi'])
            ->and(sentryExec('sentry_list_issue_comments', ['issue_id' => '1'])['comments'][0])->toMatchArray(['text' => 'Hi', 'user' => 'a@b.co']);
    });

    it('queries events across the organization with repeated fields', function () {
        sentryFake(['GET /organizations/acme/events/' => ['data' => [['title' => 'Boom', 'count()' => 3]], 'meta' => ['fields' => []]]]);

        expect(sentryExec('sentry_query_events', ['fields' => ['title', 'count()'], 'dataset' => 'errors', 'sort' => '-count()'])['rows'][0]['count()'])->toBe(3);
        expect(urldecode((string) parse_url(sentryRequest('GET /organizations/acme/events/')->url(), PHP_URL_QUERY)))->toContain('field=title&field=count()&dataset=errors');
    });

    it('creates an alert from its trigger and action groups', function () {
        sentryFake(['POST /organizations/acme/workflows/' => ['id' => '7', 'name' => 'A']]);

        sentryExec('sentry_create_alert', ['name' => 'A', 'triggers' => ['logic_type' => 'any'], 'action_filters' => [['logic_type' => 'any', 'actions' => []]], 'detector_ids' => ['3']]);

        expect(sentryBody('POST /organizations/acme/workflows/'))->toBe(['name' => 'A', 'enabled' => true, 'triggers' => ['logic_type' => 'any'], 'action_filters' => [['logic_type' => 'any', 'actions' => []]], 'detector_ids' => [3]]);
    });

    it('calls any API path on the server, and only there', function () {
        sentryFake(['GET /organizations/' => [['slug' => 'acme']]]);

        expect(sentryExec('sentry_api_request', ['method' => 'GET', 'path' => '/api/0/organizations/'])['body'])->toBe([['slug' => 'acme']])
            ->and(fn () => sentryExec('sentry_api_request', ['method' => 'GET', 'path' => 'https://evil.io/api/0/']))->toThrow(InvalidArgumentException::class)
            ->and(fn () => sentryExec('sentry_api_request', ['method' => 'GET', 'path' => '/api/0/../../admin']))->toThrow(InvalidArgumentException::class);
    });

    it('explains errors with a hint', function () {
        sentryFake([
            'GET /organizations/acme/issues/1/' => fn () => Http::response(['detail' => 'Invalid token'], 401),
            'GET /organizations/acme/issues/2/' => fn () => Http::response(['detail' => 'You do not have permission.'], 403),
            'GET /organizations/acme/issues/3/' => fn () => Http::response(['detail' => 'Slow down'], 429),
        ]);

        expect(fn () => sentryExec('sentry_get_issue', ['issue_id' => '1']))->toThrow(RuntimeException::class, 'Invalid token. Reconnect Sentry')
            ->and(fn () => sentryExec('sentry_get_issue', ['issue_id' => '2']))->toThrow(RuntimeException::class, 'lacks the scope')
            ->and(fn () => sentryExec('sentry_get_issue', ['issue_id' => '3']))->toThrow(RuntimeException::class, 'rate limiting');
    });
});

describe('sentry self-hosted address', function () {
    it('accepts sentry.io, its regions and a public https host', function () {
        expect(AbstractSentryNode::baseUrl(''))->toBe('https://sentry.io')
            ->and(AbstractSentryNode::baseUrl('https://de.sentry.io/'))->toBe('https://de.sentry.io')
            ->and(AbstractSentryNode::baseUrl('https://sentry.example.com/'))->toBe('https://sentry.example.com');
    });

    it('refuses anything else', function (string $url) {
        expect(fn () => AbstractSentryNode::baseUrl($url))->toThrow(InvalidArgumentException::class);
    })->with([
        'plain http' => 'http://sentry.example.com',
        'a user in the address' => 'https://user:pw@sentry.example.com',
        'a query' => 'https://sentry.example.com?x=1',
        'localhost' => 'https://localhost',
        'an internal name' => 'https://sentry.internal',
        'a local name' => 'https://sentry.local',
        'not a url' => 'sentry.example.com',
    ]);

    it('calls a public self-hosted server through the guard', function () {
        app()->instance(SsrfGuard::class, new SsrfGuard(fn () => ['203.0.113.10']));
        sentryFake(['GET /organizations/acme/' => ['slug' => 'acme']]);

        expect(sentryExec('sentry_get_organization', [], sentryRun(['base_url' => 'https://sentry.example.com'])))->toMatchArray(['found' => true]);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://sentry.example.com/api/0/') && $request->hasHeader('Authorization', 'Bearer sntrys_token'));
    });

    it('never sends the token to a host that resolves to a private address', function (string $address) {
        app()->instance(SsrfGuard::class, new SsrfGuard(fn () => [$address]));
        Http::fake();

        expect(fn () => sentryExec('sentry_get_organization', [], sentryRun(['base_url' => 'https://sentry.example.com'])))->toThrow(InvalidArgumentException::class, 'public host');
        Http::assertNothingSent();
    })->with(['private range' => '10.0.0.5', 'loopback' => '127.0.0.1', 'cloud metadata' => '169.254.169.254']);

    it('refuses an address given as a private ip', function () {
        Http::fake();

        expect(fn () => sentryExec('sentry_get_organization', [], sentryRun(['base_url' => 'https://192.168.1.10'])))->toThrow(InvalidArgumentException::class, 'public host');
        Http::assertNothingSent();
    });
});

describe('sentry options', function () {
    it('lists organizations, projects, teams and members, with the connection\'s organization', function () {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $query = fn (array $config = [], ?string $search = null): NodeOptionsQuery => new NodeOptionsQuery($workspace, $owner, $config, 'sntrys_token', $search, credentialData: sentryData());

        sentryFake([
            'GET /organizations/' => [['slug' => 'acme', 'name' => 'Acme Inc']],
            'GET /organizations/acme/projects/' => fn () => Http::response([['slug' => 'web', 'name' => 'Web', 'platform' => 'php']], 200, ['Link' => '<x>; rel="next"; results="true"; cursor="n"']),
            'GET /organizations/other/projects/' => [['slug' => 'api', 'name' => 'Api']],
            'GET /organizations/acme/teams/' => [['slug' => 'eng', 'name' => 'Engineering']],
            'GET /organizations/acme/members/' => [['id' => '42', 'name' => 'Ada', 'email' => 'ada@example.com']],
        ]);

        $options = new SentryOptions;
        $projects = $options->load('sentry.projects', $query());

        expect($options->sources())->toHaveCount(4)
            ->and($options->load('sentry.organizations', $query())->options[0]->toArray())->toMatchArray(['value' => 'acme', 'label' => 'Acme Inc'])
            ->and($projects->options[0]->value)->toBe('web')
            ->and($projects->nextCursor)->toBe('n')
            ->and($options->load('sentry.projects', $query(['organization' => 'other']))->options[0]->value)->toBe('api')
            ->and($options->load('sentry.teams', $query())->options[0]->value)->toBe('eng')
            ->and($options->load('sentry.members', $query())->options[0]->toArray())->toMatchArray(['value' => '42', 'label' => 'Ada']);
    });

    it('is empty without an organization, and turns a refusal into a short message', function () {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

        Http::fake();
        expect((new SentryOptions)->load('sentry.projects', new NodeOptionsQuery($workspace, $owner, [], 't', credentialData: ['api_key' => 't']))->options)->toHaveCount(0);

        sentryFake(['GET /organizations/acme/teams/' => fn () => Http::response(['detail' => 'no'], 401)]);
        expect(fn () => (new SentryOptions)->load('sentry.teams', new NodeOptionsQuery($workspace, $owner, [], 't', credentialData: sentryData())))->toThrow(ConnectorException::class, 'rejected');
    });
});

describe('sentry connection check', function () {
    $account = function (array $data = []): ConnectorCredential {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

        return ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'sentry') ?? Connector::factory()->create(['key' => 'sentry', 'name' => 'Sentry']))
            ->create(['data' => sentryData($data), 'created_by' => $owner->id]);
    };

    it('asks for the organization', function () use ($account) {
        sentryFake(['GET /organizations/acme/' => ['slug' => 'acme', 'name' => 'Acme Inc']]);

        expect(app(ConnectorCredentialTester::class)->test($account()))->toMatchArray(['ok' => true, 'account' => 'Acme Inc']);
    });

    it('fails a rejected token, an unknown organization, a bad slug and a refused address', function () use ($account) {
        sentryFake(['GET /organizations/acme/' => fn () => Http::response(['detail' => 'Invalid token'], 401)]);
        expect(app(ConnectorCredentialTester::class)->test($account())['ok'])->toBeFalse();

        sentryFake();
        expect(app(ConnectorCredentialTester::class)->test($account())['message'])->toContain('organization')
            ->and(app(ConnectorCredentialTester::class)->test($account(['organization' => 'a b']))['message'])->toContain('slug')
            ->and(app(ConnectorCredentialTester::class)->test($account(['base_url' => 'http://sentry.example.com']))['message'])->toContain('https');
    });
});

describe('sentry triggers', function () {
    $issues = fn (array $ids, array $extra = []) => sentryFake(['GET /organizations/acme/issues/' => array_map(fn (string $id): array => ['id' => $id, 'title' => "Issue {$id}", ...$extra], $ids)]);

    it('polls new issues: a baseline first, then one run for each, by first seen', function () use ($issues) {
        $trigger = sentryTrigger('sentry_list_new_issues');

        $issues(['1']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $issues(['3', '2', '1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['2', '3']);
        // An empty query is sent on purpose: left out, Sentry would only look at unresolved issues.
        parse_str((string) parse_url(sentryRequest('GET /organizations/acme/issues/')->url(), PHP_URL_QUERY), $query);
        expect($query)->toMatchArray(['sort' => 'new', 'limit' => '100', 'query' => '']);
    });

    it('fires when an issue regresses', function () use ($issues) {
        $trigger = sentryTrigger('sentry_list_regressed_issues');

        $issues(['1'], ['substatus' => 'regressed']);
        dispatch_sync(new PollTrigger($trigger));
        $issues(['2', '1'], ['substatus' => 'regressed']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->all())->toBe(['2']);
        parse_str((string) parse_url(sentryRequest('GET /organizations/acme/issues/')->url(), PHP_URL_QUERY), $query);
        expect($query['query'])->toBe('is:regressed');
    });

    it('fires when an issue changes, but not on a new event alone', function () use ($issues) {
        $trigger = sentryTrigger('sentry_list_changed_issues');

        $issues(['1'], ['status' => 'unresolved', 'lastSeen' => '2026-01-01']);
        dispatch_sync(new PollTrigger($trigger));

        $issues(['1'], ['status' => 'unresolved', 'lastSeen' => '2026-01-02']);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $issues(['1'], ['status' => 'resolved', 'lastSeen' => '2026-01-02']);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.status')->all())->toBe(['resolved']);
    });

    it('polls new events of a project', function () {
        $trigger = sentryTrigger('sentry_list_project_events', ['project' => 'web']);
        $poll = fn (array $ids) => sentryFake(['GET /projects/acme/web/events/' => array_map(fn (string $id): array => ['id' => "g{$id}", 'eventID' => $id, 'title' => 'Boom', 'groupID' => '5'], $ids)]);

        $poll(['a']);
        dispatch_sync(new PollTrigger($trigger));
        $poll(['b', 'a']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->all())->toBe(['b']);
    });

    it('polls new releases by version', function () {
        $trigger = sentryTrigger('sentry_list_releases');
        $poll = fn (array $versions) => sentryFake(['GET /organizations/acme/releases/' => array_map(fn (string $version): array => ['version' => $version, 'projects' => []], $versions)]);

        $poll(['v1']);
        dispatch_sync(new PollTrigger($trigger));
        $poll(['v2', 'v1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.version')->all())->toBe(['v2']);
    });
});
