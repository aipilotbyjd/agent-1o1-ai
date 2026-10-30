<?php

use App\Enums\Agents\ActionEffect;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Intercom\AbstractIntercomNode;
use App\Nodes\Integrations\Intercom\IntercomApiRequestNode;
use App\Nodes\Integrations\Intercom\IntercomCloseConversationNode;
use App\Nodes\Integrations\Intercom\IntercomCreateContactNode;
use App\Nodes\Integrations\Intercom\IntercomFindOrCreateCompanyNode;
use App\Nodes\Integrations\Intercom\IntercomGetContactNode;
use App\Nodes\Integrations\Intercom\IntercomListContactRepliesNode;
use App\Nodes\Integrations\Intercom\IntercomListContactsNode;
use App\Nodes\Integrations\Intercom\IntercomListNewLeadsNode;
use App\Nodes\Integrations\Intercom\IntercomListTaggedUsersNode;
use App\Nodes\Integrations\Intercom\IntercomListUpdatedTicketsNode;
use App\Nodes\Integrations\Intercom\IntercomReplyToConversationNode;
use App\Nodes\Integrations\Intercom\IntercomSendMessageNode;
use App\Nodes\Integrations\Intercom\IntercomSnoozeConversationNode;
use App\Nodes\Integrations\Intercom\IntercomUpsertContactNode;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\IntercomOptions;
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
function intercomData(array $data = []): array
{
    return ['api_key' => 'itoken', ...$data];
}

function intercomRun(array $data = []): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'intercom') ?? Connector::factory()->create(['key' => 'intercom']))->id,
        'data' => intercomData($data),
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Intercom. A key is "METHOD /path"; the value is the JSON body, or a closure
 * returning a response. Anything else answers `$fallback`, or a 404 without one.
 *
 * @param  array<string, mixed>  $routes
 * @param  array<string, mixed>|null  $fallback
 */
function fakeIntercom(array $routes, ?array $fallback = null): void
{
    Http::swap(new Factory);
    Http::fake(['*' => function (Request $request) use ($routes, $fallback) {
        $key = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return $fallback !== null ? Http::response($fallback) : Http::response(['type' => 'error.list', 'errors' => [['code' => 'not_found', 'message' => "Nothing fakes {$key}"]]], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function intercomRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.parse_url($pair[0]->url(), PHP_URL_PATH) === $route));

    expect($found)->not->toBeEmpty();

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function intercomBody(string $route): array
{
    return (array) json_decode(intercomRequest($route)->body(), true);
}

/**
 * A value for a node's required setting, by what the setting is.
 *
 * @param  array<string, mixed>  $field
 */
function intercomSample(string $key, array $field): mixed
{
    return match (true) {
        in_array($key, ['email'], true) => 'ada@example.com',
        $key === 'path' => '/brands',
        $key === 'snoozed_until' => '2030-01-01T10:00:00Z',
        str_ends_with($key, '_id') || $key === 'id' => '5ba682d23d7cf92bef87bfd4',
        isset($field['enum']) => $field['enum'][0],
        default => 'something',
    };
}

describe('intercom nodes', function () {
    it('registers every node under the intercom connector and each can run', function () {
        $registry = app(NodeRegistry::class);
        $types = array_values(array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'intercom_')));

        expect(count($types))->toBeGreaterThanOrEqual(105);

        $run = intercomRun();
        fakeIntercom([], ['id' => '7', 'type' => 'thing']);

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            expect($node)->toBeInstanceOf(AbstractIntercomNode::class)->and($node->category())->toBe('intercom');

            $schema = $node->configSchema();
            $config = [];
            foreach ($schema['required'] as $key) {
                expect($schema['properties'])->toHaveKey($key);
                $config[$key] = intercomSample($key, $schema['properties'][$key]);
            }

            $config += array_intersect_key(['name' => 'Acme', 'title' => 'Changed', 'email' => 'ada@example.com', 'contact_id' => '5ba682d23d7cf92bef87bfd4', 'company_id' => 'c1', 'user_id' => 'u1', 'description' => 'Changed', 'ticket_state_id' => '1', 'archived' => true], $schema['properties']);

            expect($node->execute($run, $config, []))->toBeArray();
            expect($node->effect($config))->toBeInstanceOf(ActionEffect::class);
        }
    });

    it('sends the token and API version, on the region', function () {
        fakeIntercom(['POST /contacts' => ['type' => 'contact', 'id' => 'c1', 'role' => 'user', 'email' => 'ada@example.com']]);

        $output = (new IntercomCreateContactNode)->execute(intercomRun(['region' => 'eu']), ['email' => 'Ada@Example.com', 'role' => 'user', 'custom_attributes' => '{"plan": "gold"}'], []);
        $request = intercomRequest('POST /contacts');

        expect($output['id'])->toBe('c1')
            ->and($request->url())->toBe('https://api.eu.intercom.io/contacts')
            ->and($request->header('Authorization')[0])->toBe('Bearer itoken')
            ->and($request->header('Intercom-Version')[0])->toBe(AbstractIntercomNode::API_VERSION)
            ->and(intercomBody('POST /contacts'))->toBe(['role' => 'user', 'email' => 'ada@example.com', 'custom_attributes' => ['plan' => 'gold']]);
    });

    it('refuses a bad id and says a missing contact is not found', function () {
        fakeIntercom(['GET /contacts/c1' => fn () => Http::response(['errors' => [['code' => 'not_found', 'message' => 'Not found']]], 404)]);

        expect((new IntercomGetContactNode)->execute(intercomRun(), ['contact_id' => 'c1'], []))->toBe(['found' => false, 'id' => 'c1'])
            ->and(fn () => (new IntercomGetContactNode)->execute(intercomRun(), ['contact_id' => '../admins'], []))->toThrow(InvalidArgumentException::class);
    });

    it('lists across cursor pages', function () {
        $pages = 0;
        fakeIntercom(['GET /contacts' => function (Request $request) use (&$pages) {
            $pages++;

            return $pages === 1
                ? ['data' => [['id' => 'c1']], 'pages' => ['next' => ['starting_after' => 'abc']]]
                : ['data' => [['id' => 'c2']], 'pages' => ['next' => null]];
        }]);

        $output = (new IntercomListContactsNode)->execute(intercomRun(), ['limit' => 50], []);

        expect($output['count'])->toBe(2)->and(array_column($output['contacts'], 'id'))->toBe(['c1', 'c2']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'starting_after=abc'));
    });

    it('updates the contact it finds and creates the one it does not', function () {
        fakeIntercom(['POST /contacts/search' => ['data' => [['id' => 'c1']]], 'PUT /contacts/c1' => ['id' => 'c1', 'name' => 'Ada']]);
        $updated = (new IntercomUpsertContactNode)->execute(intercomRun(), ['email' => 'ada@example.com', 'name' => 'Ada'], []);
        $search = intercomBody('POST /contacts/search');

        fakeIntercom(['POST /contacts/search' => ['data' => []], 'POST /contacts' => ['id' => 'c2']]);
        $created = (new IntercomUpsertContactNode)->execute(intercomRun(), ['email' => 'new@example.com'], []);

        expect($updated)->toMatchArray(['created' => false, 'id' => 'c1'])
            ->and($search['query'])->toBe(['field' => 'email', 'operator' => '=', 'value' => 'ada@example.com'])
            ->and($created)->toMatchArray(['created' => true, 'id' => 'c2'])
            ->and(fn () => (new IntercomUpsertContactNode)->execute(intercomRun(), ['name' => 'Ada'], []))->toThrow(InvalidArgumentException::class);
    });

    it('finds a company by id before creating it', function () {
        fakeIntercom(['GET /companies' => fn () => Http::response(['errors' => [['code' => 'company_not_found', 'message' => 'x']]], 404), 'POST /companies' => ['id' => 'x1', 'company_id' => 'acme']]);

        expect((new IntercomFindOrCreateCompanyNode)->execute(intercomRun(), ['company_id' => 'acme', 'name' => 'Acme'], []))->toMatchArray(['created' => true, 'id' => 'x1']);
    });

    it('replies, closes and snoozes as the teammate given, or the token owner', function () {
        fakeIntercom(['GET /me' => ['id' => '99'], 'POST /conversations/55/reply' => ['id' => '55', 'state' => 'open'], 'POST /conversations/55/parts' => ['id' => '55', 'state' => 'closed']]);

        $reply = (new IntercomReplyToConversationNode)->execute(intercomRun(), ['conversation_id' => '55', 'body' => 'Hello', 'message_type' => 'note', 'admin_id' => '7'], []);
        $close = (new IntercomCloseConversationNode)->execute(intercomRun(), ['conversation_id' => '55'], []);

        expect($reply['state'])->toBe('open')
            ->and(intercomBody('POST /conversations/55/reply'))->toBe(['message_type' => 'note', 'type' => 'admin', 'admin_id' => '7', 'body' => 'Hello'])
            ->and($close['state'])->toBe('closed')
            ->and(intercomBody('POST /conversations/55/parts'))->toBe(['message_type' => 'close', 'type' => 'admin', 'admin_id' => '99']);

        fakeIntercom(['GET /me' => ['id' => '99'], 'POST /conversations/55/parts' => ['id' => '55', 'state' => 'snoozed']]);
        (new IntercomSnoozeConversationNode)->execute(intercomRun(), ['conversation_id' => '55', 'snoozed_until' => '2030-01-01T00:00:00Z'], []);

        expect(intercomBody('POST /conversations/55/parts'))->toMatchArray(['message_type' => 'snoozed', 'snoozed_until' => 1893456000]);
    });

    it('sends a new message from a teammate', function () {
        fakeIntercom(['GET /me' => ['id' => '99'], 'POST /messages' => ['type' => 'user_message', 'id' => 'm1', 'conversation_id' => '55']]);

        $output = (new IntercomSendMessageNode)->execute(intercomRun(), ['contact_id' => 'c1', 'body' => 'Hi', 'message_type' => 'email', 'subject' => 'Hello'], []);

        expect($output['id'])->toBe('m1')->and(intercomBody('POST /messages'))->toMatchArray(['message_type' => 'email', 'template' => 'plain', 'from' => ['type' => 'admin', 'id' => 99], 'to' => ['type' => 'user', 'id' => 'c1']]);
    });

    it('makes a free-form call and refuses a path that leaves the API', function () {
        fakeIntercom(['GET /brands' => ['data' => []]]);

        expect((new IntercomApiRequestNode)->execute(intercomRun(), ['path' => '/brands'], [])['status'])->toBe(200)
            ->and(fn () => (new IntercomApiRequestNode)->execute(intercomRun(), ['path' => 'https://evil.example.net/x'], []))->toThrow(InvalidArgumentException::class);
    });

    it('explains an error with a hint', function () {
        fakeIntercom(['GET /contacts/a' => fn () => Http::response(['errors' => [['code' => 'unauthorized', 'message' => 'Access Token Invalid']]], 401), 'GET /contacts/b' => fn () => Http::response(['errors' => [['message' => 'No']]], 403), 'GET /contacts/c' => fn () => Http::response(['errors' => [['message' => 'Slow']]], 429)]);

        expect(fn () => (new IntercomGetContactNode)->execute(intercomRun(), ['contact_id' => 'a'], []))->toThrow(RuntimeException::class, 'Reconnect Intercom')
            ->and(fn () => (new IntercomGetContactNode)->execute(intercomRun(), ['contact_id' => 'b'], []))->toThrow(RuntimeException::class, 'lacks the permission')
            ->and(fn () => (new IntercomGetContactNode)->execute(intercomRun(), ['contact_id' => 'c'], []))->toThrow(RuntimeException::class, 'rate limiting');
    });

    it('checks a connection against who the token belongs to', function () {
        $credential = fn () => ConnectorCredential::factory()->create(['connector_id' => (Connector::query()->firstWhere('key', 'intercom') ?? Connector::factory()->create(['key' => 'intercom', 'name' => 'Intercom']))->id, 'data' => intercomData()]);

        fakeIntercom(['GET /me' => ['id' => '99', 'email' => 'ada@acme.com', 'app' => ['name' => 'Acme']]]);
        $good = app(ConnectorCredentialTester::class)->test($credential());

        fakeIntercom(['GET /me' => fn () => Http::response([], 401)]);
        $refused = app(ConnectorCredentialTester::class)->test($credential());

        expect($good)->toMatchArray(['ok' => true, 'account' => 'Acme'])->and($refused['ok'])->toBeFalse();
    });
});

describe('intercom options', function () {
    it('lists teammates, tags and ticket types', function () {
        fakeIntercom([
            'GET /admins' => ['admins' => [['id' => '7', 'name' => 'Ada', 'email' => 'ada@acme.com']]],
            'GET /tags' => ['data' => [['id' => '1', 'name' => 'VIP']]],
            'GET /ticket_types' => ['data' => [['id' => '3', 'name' => 'Bug']]],
        ]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = new NodeOptionsQuery($workspace, User::factory()->create(), [], 'itoken', credentialData: intercomData());
        $source = app(IntercomOptions::class);

        expect($source->load('intercom.admins', $query)->toArray()['options'])->toBe([['value' => '7', 'label' => 'Ada', 'description' => 'ada@acme.com']])
            ->and($source->load('intercom.tags', $query)->toArray()['options'])->toBe([['value' => '1', 'label' => 'VIP']])
            ->and($source->load('intercom.ticket_types', $query)->toArray()['options'])->toBe([['value' => '3', 'label' => 'Bug']]);
    });
});

describe('intercom triggers', function () {
    it('searches on the time and role, with one filter or several', function () {
        fakeIntercom(['POST /contacts/search' => ['data' => [['id' => 'l1', 'role' => 'lead']]]]);

        $output = (new IntercomListNewLeadsNode)->execute(intercomRun(), ['changed_within_minutes' => 60], []);
        $body = intercomBody('POST /contacts/search');

        expect($output['contacts'][0]['id'])->toBe('l1')
            ->and($body['query']['operator'])->toBe('AND')
            ->and($body['query']['value'][0])->toBe(['field' => 'role', 'operator' => '=', 'value' => 'lead'])
            ->and($body['query']['value'][1]['field'])->toBe('created_at')
            ->and($body['query']['value'][1]['operator'])->toBe('>');
    });

    it('treats every contact reply and ticket change as its own event', function () {
        fakeIntercom(['POST /conversations/search' => ['conversations' => [['id' => '55', 'statistics' => ['last_contact_reply_at' => 100]]]]]);
        $replies = new IntercomListContactRepliesNode;
        $output = $replies->execute(intercomRun(), [], []);
        $tickets = new IntercomListUpdatedTicketsNode;

        expect(intercomBody('POST /conversations/search')['query']['field'])->toBe('statistics.last_contact_reply_at')
            ->and($replies->triggerItemId($output['conversations'][0]))->toBe('55:100')
            ->and($tickets->triggerItemId(['id' => '1', 'updated_at' => 1]))->not->toBe($tickets->triggerItemId(['id' => '1', 'updated_at' => 2]));
    });

    it('fires once per contact for a tag', function () {
        fakeIntercom(['POST /contacts/search' => ['data' => [['id' => 'u1']]]]);
        $node = new IntercomListTaggedUsersNode;
        $output = $node->execute(intercomRun(), ['tag_id' => '123'], []);

        expect(intercomBody('POST /contacts/search')['query']['value'][1])->toBe(['field' => 'tag_id', 'operator' => '=', 'value' => '123'])
            ->and($node->triggerItemId($output['contacts'][0]))->toBe('u1')
            ->and(fn () => $node->execute(intercomRun(), [], []))->toThrow(InvalidArgumentException::class);
    });

    it('polls: a baseline first, then one run for each, and has no webhook to register', function () {
        Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
        fakeIntercom([]);

        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'intercom') ?? Connector::factory()->create(['key' => 'intercom']))
            ->create(['data' => intercomData(), 'created_by' => $owner->id]);
        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'intercom_list_new_conversations', 'config' => ['credential_id' => $credential->id, 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);
        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();

        fakeIntercom(['POST /conversations/search' => ['conversations' => [['id' => '1']]]]);
        app()->call([new PollTrigger($trigger), 'handle']);
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        fakeIntercom(['POST /conversations/search' => ['conversations' => [['id' => '2'], ['id' => '1']]]]);
        app()->call([new PollTrigger($trigger->fresh()), 'handle']);

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->values()->all())->toBe(['2'])
            ->and(ProviderWatch::query()->count())->toBe(0);
    });
});
