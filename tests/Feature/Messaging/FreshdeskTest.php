<?php

use App\Enums\Agents\ActionEffect;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Freshdesk\AbstractFreshdeskNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskAddNoteNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskAddTicketTagsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskApiRequestNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskBulkUpdateTicketsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskCreateTicketNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskDeleteTicketNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetAttachmentNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskGetTicketNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListNewNotesNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListNewTicketsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListTicketsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskListUpdatedContactsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskMergeTicketsNode;
use App\Nodes\Integrations\Freshdesk\FreshdeskSearchTicketsNode;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\FreshdeskOptions;
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
function freshdeskData(array $data = []): array
{
    return ['subdomain' => 'acme', 'api_key' => 'fkey', ...$data];
}

function freshdeskRun(array $data = []): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'freshdesk') ?? Connector::factory()->create(['key' => 'freshdesk']))->id,
        'data' => freshdeskData($data),
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Freshdesk. A key is "METHOD /path" under /api/v2; the value is the JSON body,
 * or a closure returning a response. Anything else answers `$fallback`, or a 404.
 *
 * @param  array<string, mixed>  $routes
 * @param  array<mixed>|null  $fallback
 */
function fakeFreshdesk(array $routes, ?array $fallback = null): void
{
    Http::swap(new Factory);
    Http::fake(['*' => function (Request $request) use ($routes, $fallback) {
        $key = $request->method().' '.preg_replace('~^/api/v2~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return $fallback !== null ? Http::response($fallback) : Http::response(['description' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function freshdeskRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/api/v2~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->not->toBeEmpty();

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function freshdeskBody(string $route): array
{
    return (array) json_decode(freshdeskRequest($route)->body(), true);
}

/**
 * @return array<string, string>
 */
function freshdeskQuery(string $route): array
{
    parse_str((string) parse_url(freshdeskRequest($route)->url(), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * @return array<string, mixed>
 */
function freshdeskTicket(int $id = 101, array $extra = []): array
{
    return ['id' => $id, 'subject' => 'Cannot log in', 'status' => 2, 'priority' => 3, 'requester_id' => 5, 'tags' => ['vip'], 'created_at' => now()->subMinutes(10)->utc()->format('Y-m-d\TH:i:s\Z'), 'updated_at' => now()->subMinutes(5)->utc()->format('Y-m-d\TH:i:s\Z'), ...$extra];
}

/**
 * A value for a node's required setting, by what the setting is.
 *
 * @param  array<string, mixed>  $field
 */
function freshdeskSample(string $key, array $field): mixed
{
    return match (true) {
        $key === 'email' => 'ada@example.com',
        $key === 'to_emails' => 'ada@example.com',
        $key === 'path' => '/settings/helpdesk',
        in_array($key, ['ratings', 'data'], true) => ['a' => 1],
        $key === 'schema_id' => '30',
        $key === 'record_id' => 'BKG-1',
        str_ends_with($key, '_ids') => '101, 102',
        str_ends_with($key, '_id') => '101',
        isset($field['enum']) => $field['enum'][0],
        default => 'something',
    };
}

describe('freshdesk nodes', function () {
    it('registers every node under the freshdesk connector and each can run', function () {
        $registry = app(NodeRegistry::class);
        $types = array_values(array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'freshdesk_')));

        expect(count($types))->toBeGreaterThanOrEqual(160);

        $run = freshdeskRun();
        fakeFreshdesk([], ['id' => 1]);

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            expect($node)->toBeInstanceOf(AbstractFreshdeskNode::class)->and($node->category())->toBe('freshdesk');

            $schema = $node->configSchema();
            $config = [];
            foreach ($schema['required'] as $key) {
                expect($schema['properties'])->toHaveKey($key);
                $config[$key] = freshdeskSample($key, $schema['properties'][$key]);
            }

            $config += array_intersect_key(['subject' => 'Changed', 'name' => 'Acme', 'title' => 'Changed', 'email' => 'ada@example.com', 'description' => 'Changed', 'status' => '2', 'time_spent' => '01:00', 'ticket_type' => 'x', 'occasional' => true, 'label' => 'x'], $schema['properties']);

            expect($node->execute($run, $config, []))->toBeArray();
            expect($node->effect($config))->toBeInstanceOf(ActionEffect::class);
        }
    });

    it('creates a ticket on the subdomain with the API key login', function () {
        fakeFreshdesk(['POST /tickets' => freshdeskTicket()]);

        $output = (new FreshdeskCreateTicketNode)->execute(freshdeskRun(), ['subject' => 'Cannot log in', 'description' => 'Help', 'email' => 'Ada@Example.com', 'priority' => '3', 'tags' => 'vip, billing', 'custom_fields' => '{"cf_plan": "Gold"}'], []);
        $request = freshdeskRequest('POST /tickets');

        expect($output)->toMatchArray(['id' => 101, 'status_name' => 'Open', 'priority_name' => 'High'])
            ->and($request->url())->toBe('https://acme.freshdesk.com/api/v2/tickets')
            ->and($request->header('Authorization')[0])->toBe('Basic '.base64_encode('fkey:X'))
            ->and(freshdeskBody('POST /tickets'))->toMatchArray(['status' => 2, 'priority' => 3, 'email' => 'ada@example.com', 'tags' => ['vip', 'billing'], 'custom_fields' => ['cf_plan' => 'Gold']])
            ->and(fn () => (new FreshdeskCreateTicketNode)->execute(freshdeskRun(), ['subject' => 'x', 'description' => 'y'], []))->toThrow(InvalidArgumentException::class);
    });

    it('takes a pasted subdomain, and refuses another host or a bad id', function () {
        fakeFreshdesk([]);

        expect(AbstractFreshdeskNode::apiRoot('https://Acme.freshdesk.com/a/tickets'))->toBe('https://acme.freshdesk.com/api/v2')
            ->and(fn () => AbstractFreshdeskNode::apiRoot('evil.example.net/x?'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new FreshdeskGetTicketNode)->execute(freshdeskRun(), ['ticket_id' => '../agents'], []))->toThrow(InvalidArgumentException::class);
    });

    it('says a missing ticket is not found, and embeds what was asked for', function () {
        fakeFreshdesk(['GET /tickets/101' => fn () => Http::response(['description' => 'x'], 404)]);
        $missing = (new FreshdeskGetTicketNode)->execute(freshdeskRun(), ['ticket_id' => '101'], []);

        fakeFreshdesk(['GET /tickets/101' => freshdeskTicket(101, ['requester' => ['name' => 'Ada']])]);
        $found = (new FreshdeskGetTicketNode)->execute(freshdeskRun(), ['ticket_id' => '101', 'include' => 'requester'], []);

        expect($missing)->toBe(['found' => false, 'id' => '101'])
            ->and($found)->toMatchArray(['found' => true, 'requester' => ['name' => 'Ada']])
            ->and(freshdeskQuery('GET /tickets/101')['include'])->toBe('requester');
    });

    it('lists across pages', function () {
        $page = 0;
        fakeFreshdesk(['GET /tickets' => function () use (&$page) {
            $page++;

            return $page === 1 ? array_map(fn (int $id): array => freshdeskTicket($id), range(1, 100)) : [freshdeskTicket(101)];
        }]);

        $output = (new FreshdeskListTicketsNode)->execute(freshdeskRun(), ['limit' => 500], []);

        expect($output['count'])->toBe(101)->and($page)->toBe(2);
    });

    it('quotes a filter search once', function () {
        fakeFreshdesk(['GET /search/tickets' => ['results' => [freshdeskTicket()], 'total' => 1]]);

        $output = (new FreshdeskSearchTicketsNode)->execute(freshdeskRun(), ['query' => '"priority:3 AND status:2"'], []);

        expect($output['count'])->toBe(1)->and(freshdeskQuery('GET /search/tickets')['query'])->toBe('"priority:3 AND status:2"');
    });

    it('adds tags to the ones a ticket has, and merges with the primary included', function () {
        fakeFreshdesk(['GET /tickets/101' => freshdeskTicket(101, ['tags' => ['vip']]), 'PUT /tickets/101' => freshdeskTicket(101, ['tags' => ['vip', 'new']]), 'PUT /tickets/merge' => []]);

        (new FreshdeskAddTicketTagsNode)->execute(freshdeskRun(), ['ticket_id' => '101', 'tags' => 'new, vip'], []);
        $merge = (new FreshdeskMergeTicketsNode)->execute(freshdeskRun(), ['primary_id' => '101', 'ticket_ids' => '102,103', 'note' => 'Merged'], []);

        expect(freshdeskBody('PUT /tickets/101'))->toBe(['tags' => ['vip', 'new']])
            ->and($merge)->toMatchArray(['merged' => true, 'primary_id' => 101])
            ->and(freshdeskBody('PUT /tickets/merge'))->toMatchArray(['primary_id' => 101, 'ticket_ids' => [101, 102, 103], 'note_in_primary' => ['body' => 'Merged', 'private' => true]]);
    });

    it('keeps notes private by default', function () {
        fakeFreshdesk(['POST /tickets/101/notes' => ['id' => 5, 'private' => true, 'body_text' => 'Checked']]);

        $output = (new FreshdeskAddNoteNode)->execute(freshdeskRun(), ['ticket_id' => '101', 'body' => 'Checked'], []);

        expect($output)->toMatchArray(['id' => 5, 'body' => 'Checked'])->and(freshdeskBody('POST /tickets/101/notes'))->toBe(['body' => 'Checked', 'private' => true]);
    });

    it('wraps a bulk change and needs something to change', function () {
        fakeFreshdesk(['POST /tickets/bulk_update' => ['job_id' => 'j1', 'href' => 'x']]);

        $output = (new FreshdeskBulkUpdateTicketsNode)->execute(freshdeskRun(), ['ticket_ids' => '1,2', 'status' => '4', 'reply' => 'Done'], []);

        expect($output['job_id'])->toBe('j1')
            ->and(freshdeskBody('POST /tickets/bulk_update'))->toBe(['bulk_action' => ['ids' => [1, 2], 'properties' => ['status' => 4], 'reply' => ['body' => 'Done']]])
            ->and(fn () => (new FreshdeskBulkUpdateTicketsNode)->execute(freshdeskRun(), ['ticket_ids' => '1'], []))->toThrow(InvalidArgumentException::class);
    });

    it('finds an attachment on a ticket or one of its conversations', function () {
        fakeFreshdesk(['GET /tickets/101' => freshdeskTicket(101, ['attachments' => [['id' => 1, 'name' => 'a.pdf']]]), 'GET /tickets/101/conversations' => [['id' => 9, 'attachments' => [['id' => 2, 'name' => 'b.pdf', 'attachment_url' => 'https://s3/b']]]]]);

        expect((new FreshdeskGetAttachmentNode)->execute(freshdeskRun(), ['ticket_id' => '101', 'attachment_id' => '2'], []))->toMatchArray(['found' => true, 'name' => 'b.pdf', 'conversation_id' => 9])
            ->and((new FreshdeskGetAttachmentNode)->execute(freshdeskRun(), ['ticket_id' => '101', 'attachment_id' => '3'], []))->toBe(['found' => false, 'id' => '3']);
    });

    it('deletes and makes free-form calls within the API', function () {
        fakeFreshdesk(['DELETE /tickets/101' => [], 'GET /settings/helpdesk' => ['primary_language' => 'en']]);

        expect((new FreshdeskDeleteTicketNode)->execute(freshdeskRun(), ['ticket_id' => '101'], []))->toBe(['deleted' => true, 'id' => '101'])
            ->and((new FreshdeskDeleteTicketNode)->effect([]))->toBe(ActionEffect::Destructive)
            ->and((new FreshdeskApiRequestNode)->execute(freshdeskRun(), ['path' => 'settings/helpdesk'], [])['body']['primary_language'])->toBe('en')
            ->and(fn () => (new FreshdeskApiRequestNode)->execute(freshdeskRun(), ['path' => '/a/../../evil'], []))->toThrow(InvalidArgumentException::class);
    });

    it('explains an error with a hint and the field problems', function () {
        fakeFreshdesk(['GET /tickets/1' => fn () => Http::response(['code' => 'invalid_credentials', 'message' => 'You have to be logged in'], 401), 'GET /tickets/2' => fn () => Http::response(['message' => 'No'], 403), 'GET /tickets/3' => fn () => Http::response(['message' => 'Slow'], 429), 'POST /tickets' => fn () => Http::response(['description' => 'Validation failed', 'errors' => [['field' => 'email', 'message' => 'bad address', 'code' => 'invalid_value']]], 400)]);

        expect(fn () => (new FreshdeskGetTicketNode)->execute(freshdeskRun(), ['ticket_id' => '1'], []))->toThrow(RuntimeException::class, 'Reconnect Freshdesk')
            ->and(fn () => (new FreshdeskGetTicketNode)->execute(freshdeskRun(), ['ticket_id' => '2'], []))->toThrow(RuntimeException::class, 'not allowed')
            ->and(fn () => (new FreshdeskGetTicketNode)->execute(freshdeskRun(), ['ticket_id' => '3'], []))->toThrow(RuntimeException::class, 'rate limiting')
            ->and(fn () => (new FreshdeskCreateTicketNode)->execute(freshdeskRun(), ['subject' => 'a', 'description' => 'b', 'email' => 'a@example.com'], []))->toThrow(RuntimeException::class, 'email: bad address');
    });

    it('checks a connection against whose key it is', function () {
        $credential = fn (array $data = []) => ConnectorCredential::factory()->create(['connector_id' => (Connector::query()->firstWhere('key', 'freshdesk') ?? Connector::factory()->create(['key' => 'freshdesk', 'name' => 'Freshdesk']))->id, 'data' => freshdeskData($data)]);

        fakeFreshdesk(['GET /agents/me' => ['id' => 5, 'contact' => ['email' => 'ada@acme.com']]]);
        $good = app(ConnectorCredentialTester::class)->test($credential());

        fakeFreshdesk(['GET /agents/me' => fn () => Http::response([], 401)]);
        $refused = app(ConnectorCredentialTester::class)->test($credential());

        expect($good)->toMatchArray(['ok' => true, 'account' => 'ada@acme.com'])
            ->and($refused['ok'])->toBeFalse()
            ->and(app(ConnectorCredentialTester::class)->test($credential(['subdomain' => 'a b']))['ok'])->toBeFalse();
    });
});

describe('freshdesk options', function () {
    it('lists groups, agents and sending addresses', function () {
        fakeFreshdesk([
            'GET /groups' => array_map(fn (int $id): array => ['id' => $id, 'name' => "Group {$id}"], range(1, 100)),
            'GET /agents' => [['id' => 5, 'contact' => ['name' => 'Ada', 'email' => 'ada@acme.com']]],
            'GET /email_configs' => [['id' => 8, 'name' => 'Support', 'reply_email' => 'help@acme.com']],
        ]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = new NodeOptionsQuery($workspace, User::factory()->create(), [], 'fkey', credentialData: freshdeskData());
        $source = app(FreshdeskOptions::class);
        $groups = $source->load('freshdesk.groups', $query)->toArray();

        expect($groups['options'][0])->toBe(['value' => '1', 'label' => 'Group 1'])
            ->and($groups['next_cursor'])->toBe('2')
            ->and($source->load('freshdesk.agents', $query)->toArray()['options'])->toBe([['value' => '5', 'label' => 'Ada', 'description' => 'ada@acme.com']])
            ->and($source->load('freshdesk.email_configs', $query)->toArray()['options'])->toBe([['value' => '8', 'label' => 'Support', 'description' => 'help@acme.com']]);
    });
});

describe('freshdesk triggers', function () {
    it('keeps only tickets created inside the look-back, newest first', function () {
        fakeFreshdesk(['GET /tickets' => [freshdeskTicket(2), freshdeskTicket(1, ['created_at' => now()->subDays(5)->utc()->format('Y-m-d\TH:i:s\Z')])]]);

        $output = (new FreshdeskListNewTicketsNode)->execute(freshdeskRun(), ['changed_within_minutes' => 60], []);

        expect(array_column($output['tickets'], 'id'))->toBe([2])
            ->and(freshdeskQuery('GET /tickets'))->toMatchArray(['order_by' => 'created_at', 'order_type' => 'desc'])
            ->and(freshdeskQuery('GET /tickets'))->toHaveKey('updated_since');
    });

    it('makes every contact change its own event', function () {
        fakeFreshdesk(['GET /contacts' => [['id' => 1, 'updated_at' => 'a']]]);
        $node = new FreshdeskListUpdatedContactsNode;
        $output = $node->execute(freshdeskRun(), [], []);

        expect($node->triggerItemId($output['contacts'][0]))->toBe('1:a')
            ->and($node->triggerItemId(['id' => 1, 'updated_at' => 'b']))->toBe('1:b');
    });

    it('picks notes off recently changed tickets', function () {
        fakeFreshdesk(['GET /tickets' => [freshdeskTicket(101)], 'GET /tickets/101/conversations' => [['id' => 7, 'source' => 2, 'body_text' => 'Note', 'created_at' => 'b'], ['id' => 8, 'source' => 0, 'body_text' => 'Reply', 'created_at' => 'a']]]);

        $output = (new FreshdeskListNewNotesNode)->execute(freshdeskRun(), [], []);

        expect(array_column($output['notes'], 'id'))->toBe([7]);
    });

    it('polls: a baseline first, then one run for each', function () {
        Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
        fakeFreshdesk([]);

        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'freshdesk') ?? Connector::factory()->create(['key' => 'freshdesk']))
            ->create(['data' => freshdeskData(), 'created_by' => $owner->id]);
        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'freshdesk_list_new_tickets', 'config' => ['credential_id' => $credential->id, 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);
        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();

        fakeFreshdesk(['GET /tickets' => [freshdeskTicket(1)]]);
        app()->call([new PollTrigger($trigger), 'handle']);
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        fakeFreshdesk(['GET /tickets' => [freshdeskTicket(2), freshdeskTicket(1)]]);
        app()->call([new PollTrigger($trigger->fresh()), 'handle']);

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->values()->all())->toBe([2]);
    });
});
