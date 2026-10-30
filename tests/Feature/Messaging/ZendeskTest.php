<?php

use App\Enums\Agents\ActionEffect;
use App\Enums\Triggers\SubscriptionStatus;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Zendesk\AbstractZendeskNode;
use App\Nodes\Integrations\Zendesk\ZendeskAddCommentNode;
use App\Nodes\Integrations\Zendesk\ZendeskAddTagsNode;
use App\Nodes\Integrations\Zendesk\ZendeskApiRequestNode;
use App\Nodes\Integrations\Zendesk\ZendeskApplyMacroNode;
use App\Nodes\Integrations\Zendesk\ZendeskAttachFileToTicketNode;
use App\Nodes\Integrations\Zendesk\ZendeskBatchUpdateTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskCreateTicketNode;
use App\Nodes\Integrations\Zendesk\ZendeskFindOrCreateUserNode;
use App\Nodes\Integrations\Zendesk\ZendeskGetTicketNode;
use App\Nodes\Integrations\Zendesk\ZendeskListRecentCommentsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListSolvedTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListUpdatedTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskListViewUpdatedTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskMergeTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskPreviewMacroNode;
use App\Nodes\Integrations\Zendesk\ZendeskRemoveTagsNode;
use App\Nodes\Integrations\Zendesk\ZendeskSearchTicketsNode;
use App\Nodes\Integrations\Zendesk\ZendeskSetTagsNode;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Triggers\Push\ZendeskSource;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ZendeskOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * @return array<string, mixed>
 */
function zendeskData(array $data = []): array
{
    return ['subdomain' => 'acme', 'email' => 'agent@acme.com', 'api_key' => 'ztoken', ...$data];
}

function zendeskRun(array $data = []): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'zendesk') ?? Connector::factory()->create(['key' => 'zendesk']))->id,
        'data' => zendeskData($data),
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Zendesk. A key is "METHOD /path" under /api/v2; the value is the JSON
 * body, or a closure returning a response. Anything else answers `$fallback`.
 *
 * @param  array<string, mixed>  $routes
 * @param  array<string, mixed>|null  $fallback  null makes an unknown route a 404
 */
function fakeZendesk(array $routes, ?array $fallback = null): void
{
    Http::swap(new Factory);
    Http::fake(['*' => function (Request $request) use ($routes, $fallback) {
        $key = $request->method().' '.preg_replace('~^/api/v2~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return $fallback !== null ? Http::response($fallback) : Http::response(['error' => 'NotFound', 'description' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function zendeskRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/api/v2~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->not->toBeEmpty();

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function zendeskBody(string $route): array
{
    return (array) json_decode(zendeskRequest($route)->body(), true);
}

/**
 * @return array<string, string>
 */
function zendeskQuery(string $route): array
{
    parse_str((string) parse_url(zendeskRequest($route)->url(), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * @return array<string, mixed>
 */
function zendeskTicket(int $id = 101, array $extra = []): array
{
    return ['id' => $id, 'subject' => 'Cannot log in', 'status' => 'open', 'priority' => 'high', 'requester_id' => 5, 'tags' => ['vip'], 'created_at' => '2026-10-30T10:00:00Z', 'updated_at' => '2026-10-30T11:00:00Z', ...$extra];
}

/**
 * A value for a node's required setting, by what the setting is.
 *
 * @param  array<string, mixed>  $field
 */
function zendeskSample(string $key, array $field): mixed
{
    return match (true) {
        $key === 'query' => 'status:open',
        $key === 'name' => 'Acme',
        $key === 'tag' => 'vip',
        $key === 'tickets' => [['subject' => 'Setup', 'comment' => ['body' => 'Hello']]],
        $key === 'to' => 'ada@example.com',
        $key === 'email' => 'ada@example.com',
        $key === 'file_url' => 'https://93.184.216.34/report.pdf',
        $key === 'path' => '/brands',
        $key === 'side_conversation_id' => '8566255a-ece5-11e8-857d-4',
        $key === 'job_id' => 'abc123',
        str_ends_with($key, '_ids') => '101, 102',
        str_ends_with($key, '_id') => '101',
        isset($field['enum']) => $field['enum'][0],
        default => 'something',
    };
}

describe('zendesk nodes', function () {
    it('registers every node under the zendesk connector and each can run', function () {
        $registry = app(NodeRegistry::class);
        $types = array_values(array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'zendesk_')));

        expect(count($types))->toBeGreaterThanOrEqual(120);

        $run = zendeskRun();
        fakeZendesk([], ['id' => 1, 'upload' => ['token' => 't1']]);

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            expect($node)->toBeInstanceOf(AbstractZendeskNode::class)->and($node->category())->toBe('zendesk');

            $schema = $node->configSchema();
            $config = [];
            foreach ($schema['required'] as $key) {
                expect($schema['properties'])->toHaveKey($key);
                $config[$key] = zendeskSample($key, $schema['properties'][$key]);
            }

            $config += array_intersect_key(['subject' => 'Changed', 'name' => 'Acme'], $schema['properties']);

            expect($node->execute($run, $config, []))->toBeArray();
            expect($node->effect($config))->toBeInstanceOf(ActionEffect::class);
        }
    });

    it('creates a ticket on the subdomain with the token login', function () {
        fakeZendesk(['POST /tickets' => ['ticket' => zendeskTicket()]]);

        $output = (new ZendeskCreateTicketNode)->execute(zendeskRun(), ['subject' => 'Cannot log in', 'comment' => 'Help', 'requester_email' => 'Ada@Example.com', 'priority' => 'high', 'tags' => 'vip, billing', 'custom_fields' => '{"360": "Gold"}'], []);
        $request = zendeskRequest('POST /tickets');

        expect($output['id'])->toBe(101)
            ->and($request->url())->toStartWith('https://acme.zendesk.com/api/v2/tickets')
            ->and($request->header('Authorization')[0])->toBe('Basic '.base64_encode('agent@acme.com/token:ztoken'))
            ->and(zendeskBody('POST /tickets')['ticket'])->toMatchArray(['subject' => 'Cannot log in', 'priority' => 'high', 'tags' => ['vip', 'billing'], 'custom_fields' => [['id' => 360, 'value' => 'Gold']], 'requester' => ['email' => 'ada@example.com'], 'comment' => ['public' => true, 'body' => 'Help']]);
    });

    it('uses a bearer token when the connection has no email', function () {
        fakeZendesk(['GET /tickets/101' => ['ticket' => zendeskTicket()]]);

        (new ZendeskGetTicketNode)->execute(zendeskRun(['email' => '']), ['ticket_id' => '101'], []);

        expect(zendeskRequest('GET /tickets/101')->header('Authorization')[0])->toBe('Bearer ztoken');
    });

    it('takes a pasted subdomain, and refuses another host or a bad id', function () {
        fakeZendesk(['GET /tickets/101' => ['ticket' => zendeskTicket()]]);

        expect(AbstractZendeskNode::apiRoot('https://Acme.zendesk.com/agent'))->toBe('https://acme.zendesk.com/api/v2')
            ->and(fn () => AbstractZendeskNode::apiRoot('evil.com/x?'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new ZendeskGetTicketNode)->execute(zendeskRun(['subdomain' => 'evil.example.net']), ['ticket_id' => '101'], []))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new ZendeskGetTicketNode)->execute(zendeskRun(), ['ticket_id' => '../users'], []))->toThrow(InvalidArgumentException::class);
    });

    it('says a missing ticket is not found', function () {
        fakeZendesk(['GET /tickets/101' => fn () => Http::response(['error' => 'RecordNotFound', 'description' => 'Not found'], 404)]);

        expect((new ZendeskGetTicketNode)->execute(zendeskRun(), ['ticket_id' => '101'], []))->toBe(['found' => false, 'id' => '101']);
    });

    it('lists across pages and never follows a next page to another host', function () {
        $page = 0;
        fakeZendesk(['GET /tickets' => function () use (&$page) {
            $page++;

            return ['tickets' => [zendeskTicket($page)], 'next_page' => $page === 1 ? 'https://acme.zendesk.com/api/v2/tickets?page=2' : 'https://evil.example.net/api/v2/tickets?page=3'];
        }]);

        $output = (new ZendeskListTicketsNode)->execute(zendeskRun(), ['limit' => 50], []);

        expect($output['count'])->toBe(2)->and($page)->toBe(2);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'evil.example.net'));
    });

    it('searches tickets inside the ticket type', function () {
        fakeZendesk(['GET /search' => ['results' => [zendeskTicket()], 'count' => 1]]);

        $output = (new ZendeskSearchTicketsNode)->execute(zendeskRun(), ['query' => 'status:open'], []);

        expect($output['tickets'][0]['id'])->toBe(101)->and(zendeskQuery('GET /search'))->toMatchArray(['query' => 'type:ticket status:open', 'sort_order' => 'desc']);
    });

    it('adds, removes and replaces tags with the method Zendesk uses for each', function () {
        fakeZendesk(['PUT /tickets/101/tags' => ['tags' => ['a', 'b']], 'DELETE /users/5/tags' => ['tags' => []], 'POST /organizations/7/tags' => ['tags' => ['c']]]);

        $added = (new ZendeskAddTagsNode)->execute(zendeskRun(), ['resource' => 'tickets', 'resource_id' => '101', 'tags' => 'b'], []);
        (new ZendeskRemoveTagsNode)->execute(zendeskRun(), ['resource' => 'users', 'resource_id' => '5', 'tags' => 'a'], []);
        (new ZendeskSetTagsNode)->execute(zendeskRun(), ['resource' => 'organizations', 'resource_id' => '7', 'tags' => 'c'], []);

        expect($added['tags'])->toBe(['a', 'b'])
            ->and(zendeskBody('DELETE /users/5/tags'))->toBe(['tags' => ['a']])
            ->and(zendeskBody('POST /organizations/7/tags'))->toBe(['tags' => ['c']])
            ->and(fn () => (new ZendeskAddTagsNode)->execute(zendeskRun(), ['resource' => 'groups', 'resource_id' => '1', 'tags' => 'x'], []))->toThrow(InvalidArgumentException::class);
    });

    it('adds a private comment on request, merges tickets and updates many', function () {
        fakeZendesk([
            'PUT /tickets/101' => ['ticket' => zendeskTicket(), 'audit' => ['id' => 9]],
            'POST /tickets/101/merge' => ['job_status' => ['id' => 'j1', 'status' => 'queued']],
            'PUT /tickets/update_many' => ['job_status' => ['id' => 'j2', 'status' => 'queued']],
        ]);

        $comment = (new ZendeskAddCommentNode)->execute(zendeskRun(), ['ticket_id' => '101', 'comment' => '<b>Note</b>', 'comment_public' => false], []);
        $merge = (new ZendeskMergeTicketsNode)->execute(zendeskRun(), ['ticket_id' => '101', 'source_ids' => '102, 103'], []);
        $many = (new ZendeskBatchUpdateTicketsNode)->execute(zendeskRun(), ['ticket_ids' => '1,2', 'status' => 'solved'], []);

        expect($comment['audit_id'])->toBe(9)
            ->and(zendeskBody('PUT /tickets/101')['ticket']['comment'])->toBe(['public' => false, 'html_body' => '<b>Note</b>'])
            ->and($merge['job_status']['id'])->toBe('j1')
            ->and(zendeskBody('POST /tickets/101/merge')['ids'])->toBe([102, 103])
            ->and($many['job_status']['id'])->toBe('j2')
            ->and(zendeskQuery('PUT /tickets/update_many')['ids'])->toBe('1,2')
            ->and(fn () => (new ZendeskBatchUpdateTicketsNode)->execute(zendeskRun(), ['ticket_ids' => '1'], []))->toThrow(InvalidArgumentException::class);
    });

    it('previews a macro without saving and applies it with a save', function () {
        fakeZendesk(['GET /tickets/101/macros/5/apply' => ['result' => ['ticket' => ['id' => 101, 'url' => 'x', 'status' => 'solved']]], 'PUT /tickets/101' => ['ticket' => zendeskTicket(101, ['status' => 'solved'])]]);

        $preview = (new ZendeskPreviewMacroNode)->execute(zendeskRun(), ['ticket_id' => '101', 'macro_id' => '5'], []);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PUT');

        $applied = (new ZendeskApplyMacroNode)->execute(zendeskRun(), ['ticket_id' => '101', 'macro_id' => '5'], []);

        expect($preview['ticket']['status'])->toBe('solved')->and($applied['status'])->toBe('solved')->and(zendeskBody('PUT /tickets/101'))->toBe(['ticket' => ['status' => 'solved']]);
    });

    it('fetches a file through the guarded client and attaches it, and refuses a private address', function () {
        fakeZendesk(['POST /uploads' => ['upload' => ['token' => 'tok', 'attachment' => ['id' => 1]]], 'PUT /tickets/101' => ['ticket' => zendeskTicket()], 'GET /report.pdf' => fn () => Http::response('%PDF', 200, ['Content-Type' => 'application/pdf'])]);

        $output = (new ZendeskAttachFileToTicketNode)->execute(zendeskRun(), ['ticket_id' => '101', 'file_url' => 'https://93.184.216.34/report.pdf', 'comment' => 'See file'], []);

        expect($output['token'])->toBe('tok')
            ->and(zendeskQuery('POST /uploads')['filename'])->toBe('report.pdf')
            ->and(zendeskBody('PUT /tickets/101')['ticket']['comment']['uploads'])->toBe(['tok'])
            ->and(fn () => (new ZendeskAttachFileToTicketNode)->execute(zendeskRun(), ['ticket_id' => '101', 'file_url' => 'http://169.254.169.254/x', 'comment' => 'x'], []))->toThrow(Exception::class);
    });

    it('finds a user before creating one', function () {
        fakeZendesk(['GET /users/search' => ['users' => []], 'POST /users' => ['user' => ['id' => 5, 'email' => 'ada@example.com']]]);
        $created = (new ZendeskFindOrCreateUserNode)->execute(zendeskRun(), ['email' => 'Ada@example.com'], []);

        fakeZendesk(['GET /users/search' => ['users' => [['id' => 5, 'email' => 'ada@example.com']]]]);
        $found = (new ZendeskFindOrCreateUserNode)->execute(zendeskRun(), ['email' => 'ada@example.com'], []);

        expect($created)->toMatchArray(['created' => true, 'id' => 5])->and($found)->toMatchArray(['created' => false, 'id' => 5]);
    });

    it('makes a free-form call and refuses a path that leaves the API', function () {
        fakeZendesk(['GET /brands' => ['brands' => []]]);

        expect((new ZendeskApiRequestNode)->execute(zendeskRun(), ['path' => '/brands'], [])['status'])->toBe(200)
            ->and((new ZendeskApiRequestNode)->effect(['method' => 'DELETE']))->toBe(ActionEffect::Destructive)
            ->and(fn () => (new ZendeskApiRequestNode)->execute(zendeskRun(), ['path' => '/../../evil'], []))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new ZendeskApiRequestNode)->execute(zendeskRun(), ['path' => 'https://evil.example.net/x'], []))->toThrow(InvalidArgumentException::class);
    });

    it('explains an error with a hint', function () {
        fakeZendesk(['GET /tickets/1' => fn () => Http::response(['error' => 'Couldn\'t authenticate you'], 401), 'GET /tickets/2' => fn () => Http::response(['error' => ['title' => 'Forbidden', 'message' => 'No']], 403), 'GET /tickets/3' => fn () => Http::response(['error' => 'Too many'], 429)]);

        expect(fn () => (new ZendeskGetTicketNode)->execute(zendeskRun(), ['ticket_id' => '1'], []))->toThrow(RuntimeException::class, 'Reconnect Zendesk')
            ->and(fn () => (new ZendeskGetTicketNode)->execute(zendeskRun(), ['ticket_id' => '2'], []))->toThrow(RuntimeException::class, 'not allowed')
            ->and(fn () => (new ZendeskGetTicketNode)->execute(zendeskRun(), ['ticket_id' => '3'], []))->toThrow(RuntimeException::class, 'rate limiting');
    });

    it('checks a connection against who the login belongs to', function () {
        $credential = fn (array $data = []) => ConnectorCredential::factory()->create(['connector_id' => (Connector::query()->firstWhere('key', 'zendesk') ?? Connector::factory()->create(['key' => 'zendesk', 'name' => 'Zendesk']))->id, 'data' => zendeskData($data)]);

        fakeZendesk(['GET /users/me' => ['user' => ['id' => 5, 'email' => 'agent@acme.com']]]);
        $good = app(ConnectorCredentialTester::class)->test($credential());

        fakeZendesk(['GET /users/me' => ['user' => ['id' => null, 'name' => 'Anonymous user']]]);
        $anonymous = app(ConnectorCredentialTester::class)->test($credential());

        fakeZendesk(['GET /users/me' => fn () => Http::response([], 401)]);
        $refused = app(ConnectorCredentialTester::class)->test($credential());

        expect($good)->toMatchArray(['ok' => true, 'account' => 'agent@acme.com'])
            ->and($anonymous['ok'])->toBeFalse()
            ->and($refused['ok'])->toBeFalse()
            ->and(app(ConnectorCredentialTester::class)->test($credential(['subdomain' => 'a b']))['ok'])->toBeFalse();
    });
});

describe('zendesk options', function () {
    it('lists groups, agents, views and organizations', function () {
        fakeZendesk([
            'GET /groups' => ['groups' => [['id' => 1, 'name' => 'Support']], 'next_page' => 'https://acme.zendesk.com/api/v2/groups?page=2'],
            'GET /users/search' => ['users' => [['id' => 5, 'name' => 'Ada', 'email' => 'ada@acme.com']]],
            'GET /views' => ['views' => [['id' => 7, 'title' => 'Unassigned']]],
            'GET /organizations/autocomplete' => ['organizations' => [['id' => 9, 'name' => 'Acme']]],
        ]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = new NodeOptionsQuery($workspace, User::factory()->create(), [], 'ztoken', credentialData: zendeskData());
        $source = app(ZendeskOptions::class);

        $groups = $source->load('zendesk.groups', $query)->toArray();

        expect($groups['options'])->toBe([['value' => '1', 'label' => 'Support']])
            ->and($groups['next_cursor'])->toBe('2')
            ->and($source->load('zendesk.agents', $query)->toArray()['options'])->toBe([['value' => '5', 'label' => 'Ada', 'description' => 'ada@acme.com']])
            ->and($source->load('zendesk.views', $query)->toArray()['options'])->toBe([['value' => '7', 'label' => 'Unassigned']])
            ->and($source->load('zendesk.organizations', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'ztoken', search: 'Ac', credentialData: zendeskData()))->toArray()['options'])->toBe([['value' => '9', 'label' => 'Acme']]);
    });
});

/**
 * A published workflow whose first step is a Zendesk reader switched on as a trigger.
 *
 * @param  array<string, mixed>  $routes
 * @param  array<string, mixed>  $nodeConfig
 */
function zendeskTrigger(string $nodeType = 'zendesk_list_new_tickets', array $routes = [], bool $sync = true, array $nodeConfig = []): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeZendesk($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'zendesk') ?? Connector::factory()->create(['key' => 'zendesk']))
        ->create(['data' => zendeskData(), 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
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

describe('zendesk triggers', function () {
    it('polls new tickets: a baseline first, then one run for each', function () {
        $trigger = zendeskTrigger(sync: false);
        $poll = fn (array $ids) => fakeZendesk(['GET /search' => ['results' => array_map(fn (int $id): array => zendeskTicket($id), $ids)]]);

        $poll([1]);
        app()->call([new PollTrigger($trigger), 'handle']);
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([3, 2, 1]);
        app()->call([new PollTrigger($trigger->fresh()), 'handle']);

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe([2, 3]);
    });

    it('asks search for the right time and status, and tells changes apart', function () {
        fakeZendesk(['GET /search' => ['results' => [zendeskTicket()]]]);
        $updated = new ZendeskListUpdatedTicketsNode;
        $updated->execute(zendeskRun(), ['changed_within_minutes' => 60], []);
        $query = zendeskQuery('GET /search');

        fakeZendesk(['GET /search' => ['results' => [zendeskTicket()]]]);
        $solved = new ZendeskListSolvedTicketsNode;
        $output = $solved->execute(zendeskRun(), ['search_filter' => 'group:Support'], []);

        expect($query['query'])->toContain('type:ticket updated>')
            ->and($query['sort_by'])->toBe('updated_at')
            ->and(zendeskQuery('GET /search')['query'])->toContain('status:solved')->toContain('group:Support')
            ->and($output['tickets'][0]['id'])->toBe(101)
            ->and($updated->triggerItemId(['id' => 1, 'updated_at' => 'a']))->not->toBe($updated->triggerItemId(['id' => 1, 'updated_at' => 'b']))
            ->and($solved->triggerItemId(['id' => 1, 'updated_at' => 'a']))->toBe($solved->triggerItemId(['id' => 1, 'updated_at' => 'b']));
    });

    it('picks comments out of the account-wide audits', function () {
        fakeZendesk(['GET /ticket_audits' => ['audits' => [['id' => 1, 'ticket_id' => 101, 'created_at' => 'then', 'events' => [['id' => 50, 'type' => 'Comment', 'body' => 'Hi', 'public' => true], ['id' => 51, 'type' => 'Change', 'field_name' => 'status']]]]]]);

        $output = (new ZendeskListRecentCommentsNode)->execute(zendeskRun(), [], []);

        expect($output['count'])->toBe(1)->and($output['comments'][0])->toMatchArray(['id' => 50, 'ticket_id' => 101, 'body' => 'Hi'])
            ->and(zendeskQuery('GET /ticket_audits')['page']['size'])->toBe('100');
    });

    it('tells a change in a view from the ticket entering it', function () {
        $node = new ZendeskListViewUpdatedTicketsNode;

        expect($node->triggerItemId(['id' => 1, 'updated_at' => 'a']))->not->toBe($node->triggerItemId(['id' => 1, 'updated_at' => 'b']))
            ->and($node->triggerName())->toBe('Zendesk: Updated ticket in view');
    });
});

describe('zendesk instant delivery', function () {
    it('creates a webhook for the event with a token URL, and starts a poll when it arrives', function () {
        $trigger = zendeskTrigger(routes: ['POST /webhooks' => ['webhook' => ['id' => 'wh1']]]);
        $watch = ProviderWatch::query()->sole();
        $webhook = zendeskBody('POST /webhooks')['webhook'];

        expect($watch->resource_key)->toBe('ticket.created')
            ->and($watch->external_id)->toBe('wh1')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($webhook['endpoint'])->toBe(url('/api/hooks/apps/zendesk').'?token='.$watch->secret)
            ->and($webhook['subscriptions'])->toBe(['zen:event-type:ticket.created'])
            ->and($webhook)->toMatchArray(['http_method' => 'POST', 'request_format' => 'json', 'status' => 'active']);

        $body = ['id' => 'ev1', 'type' => 'zen:event-type:ticket.created', 'subject' => 'zen:ticket:101', 'detail' => ['id' => '101']];
        test()->postJson('/api/hooks/apps/zendesk?token='.$watch->secret, $body)->assertOk();
        test()->postJson('/api/hooks/apps/zendesk?token='.$watch->secret, $body)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('listens for many events on a changed-ticket trigger and the right one on others', function () {
        zendeskTrigger('zendesk_list_updated_tickets', ['POST /webhooks' => ['webhook' => ['id' => 'wh2']]]);

        expect(ProviderWatch::query()->sole()->resource_key)->toBe('ticket.changed')
            ->and(zendeskBody('POST /webhooks')['webhook']['subscriptions'])->toContain('zen:event-type:ticket.status_changed', 'zen:event-type:ticket.comment_added');

        ProviderWatch::query()->delete();
        zendeskTrigger('zendesk_list_new_users', ['POST /webhooks' => ['webhook' => ['id' => 'wh3']]]);

        expect(zendeskBody('POST /webhooks')['webhook']['subscriptions'])->toBe(['zen:event-type:user.created']);
    });

    it('refuses a wrong or missing token and is closed while the switch is off', function () {
        zendeskTrigger(routes: ['POST /webhooks' => ['webhook' => ['id' => 'wh1']]]);
        $watch = ProviderWatch::query()->sole();
        $body = ['id' => 'ev1', 'type' => 'zen:event-type:ticket.created'];

        test()->postJson('/api/hooks/apps/zendesk?token=nope', $body)->assertUnauthorized();
        test()->postJson('/api/hooks/apps/zendesk', $body)->assertUnauthorized();
        config(['triggers.push.zendesk.enabled' => false]);
        test()->postJson('/api/hooks/apps/zendesk?token='.$watch->secret, $body)->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('keeps polling when Zendesk refuses the webhook', function () {
        $trigger = zendeskTrigger(routes: ['POST /webhooks' => fn () => Http::response(['errors' => [['title' => 'Forbidden']]], 403)]);

        expect($trigger->subscription->status)->not->toBe(SubscriptionStatus::Active)->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('makes the webhook again when Zendesk lost it, and removes it with the watch', function () {
        zendeskTrigger(routes: ['POST /webhooks' => ['webhook' => ['id' => 'wh1']]]);
        $watch = ProviderWatch::query()->sole();
        $source = app(ZendeskSource::class);

        fakeZendesk(['GET /webhooks/wh1' => fn () => Http::response(['errors' => []], 404), 'POST /webhooks' => ['webhook' => ['id' => 'wh2']], 'DELETE /webhooks/wh1' => []]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe('wh2');

        fakeZendesk(['GET /webhooks/wh2' => ['webhook' => ['id' => 'wh2', 'status' => 'active']]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');

        fakeZendesk(['DELETE /webhooks/wh2' => []]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
