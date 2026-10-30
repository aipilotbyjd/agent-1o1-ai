<?php

use App\Enums\Agents\ActionEffect;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Apollo\AbstractApolloNode;
use App\Nodes\Integrations\Apollo\ApolloActivateSequenceNode;
use App\Nodes\Integrations\Apollo\ApolloAddContactsToSequenceNode;
use App\Nodes\Integrations\Apollo\ApolloAddToListNode;
use App\Nodes\Integrations\Apollo\ApolloBulkCreateContactsNode;
use App\Nodes\Integrations\Apollo\ApolloBulkEnrichPeopleNode;
use App\Nodes\Integrations\Apollo\ApolloCompleteTaskNode;
use App\Nodes\Integrations\Apollo\ApolloCreateAccountNode;
use App\Nodes\Integrations\Apollo\ApolloCreateCallNode;
use App\Nodes\Integrations\Apollo\ApolloCreateContactNode;
use App\Nodes\Integrations\Apollo\ApolloCreateCustomFieldNode;
use App\Nodes\Integrations\Apollo\ApolloCreateDealNode;
use App\Nodes\Integrations\Apollo\ApolloCreateListNode;
use App\Nodes\Integrations\Apollo\ApolloCreateSequenceNode;
use App\Nodes\Integrations\Apollo\ApolloCreateTaskNode;
use App\Nodes\Integrations\Apollo\ApolloEnrichOrganizationNode;
use App\Nodes\Integrations\Apollo\ApolloEnrichPersonNode;
use App\Nodes\Integrations\Apollo\ApolloExportConversationsNode;
use App\Nodes\Integrations\Apollo\ApolloFindContactNode;
use App\Nodes\Integrations\Apollo\ApolloFindOrCreateContactNode;
use App\Nodes\Integrations\Apollo\ApolloGetCurrentUserNode;
use App\Nodes\Integrations\Apollo\ApolloListChangedDealsNode;
use App\Nodes\Integrations\Apollo\ApolloListContactStagesNode;
use App\Nodes\Integrations\Apollo\ApolloListDealsNode;
use App\Nodes\Integrations\Apollo\ApolloListListsNode;
use App\Nodes\Integrations\Apollo\ApolloListNewContactsNode;
use App\Nodes\Integrations\Apollo\ApolloListNewDealsNode;
use App\Nodes\Integrations\Apollo\ApolloPollAssistantTaskNode;
use App\Nodes\Integrations\Apollo\ApolloQueryReportNode;
use App\Nodes\Integrations\Apollo\ApolloRunAssistantTaskNode;
use App\Nodes\Integrations\Apollo\ApolloSearchCallsNode;
use App\Nodes\Integrations\Apollo\ApolloSearchEmailsNode;
use App\Nodes\Integrations\Apollo\ApolloSearchPeopleNode;
use App\Nodes\Integrations\Apollo\ApolloSearchTasksNode;
use App\Nodes\Integrations\Apollo\ApolloSendEmailNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateContactNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateContactStagesNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateDealNode;
use App\Nodes\Integrations\Apollo\ApolloUpdateSequenceContactStatusNode;
use App\Nodes\Integrations\Apollo\ApolloUpsertContactNode;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ApolloOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function apolloRun(): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'apollo') ?? Connector::factory()->create(['key' => 'apollo']))->id,
        'data' => ['api_key' => 'ap-key'],
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Apollo. A key is "METHOD /path" under /api/v1.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeApollo(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.apollo.io/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.preg_replace('~^/api/v1~', '', rawurldecode((string) parse_url($request->url(), PHP_URL_PATH)));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['error' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function apolloRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/api/v1~', '', rawurldecode((string) parse_url($pair[0]->url(), PHP_URL_PATH))) === $route));

    expect($found)->not->toBeEmpty();

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function apolloBody(string $route): array
{
    return (array) json_decode(apolloRequest($route)->body(), true);
}

function apolloQuery(string $route): string
{
    return rawurldecode((string) parse_url(apolloRequest($route)->url(), PHP_URL_QUERY));
}

describe('apollo registry and connector', function () {
    it('registers every node under the apollo connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'apollo_'));

        expect(count($types))->toBeGreaterThanOrEqual(90);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractApolloNode::class)->and($registry->resolve($type)->category())->toBe('apollo');
        }
    });

    it('checks the key against the health endpoint, and notices a refused one', function () {
        $connector = Connector::factory()->create(['key' => 'apollo', 'name' => 'Apollo']);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)->create(['data' => ['api_key' => 'ap-key'], 'created_by' => $owner->id]);

        fakeApollo(['GET /auth/health' => ['healthy' => true, 'is_logged_in' => true]]);
        $good = app(ConnectorCredentialTester::class)->test($credential);

        expect($good['ok'])->toBeTrue()->and(apolloRequest('GET /auth/health')->header('x-api-key')[0])->toBe('ap-key');

        fakeApollo(['GET /auth/health' => ['healthy' => true, 'is_logged_in' => false]]);

        expect(app(ConnectorCredentialTester::class)->test($credential)['ok'])->toBeFalse();
    });
});

describe('apollo search and enrich', function () {
    it('searches people, sending lists as name[]= values and the key in a header', function () {
        fakeApollo(['POST /mixed_people/api_search' => ['total_entries' => 2, 'people' => [['id' => 'p1', 'first_name' => 'Ada']]]]);

        $output = (new ApolloSearchPeopleNode)->execute(apolloRun(), ['person_titles' => ['Sales Manager', 'VP'], 'person_locations' => 'Austin', 'more_filters' => ['revenue_range[min]' => 100], 'limit' => 500], []);
        $query = apolloQuery('POST /mixed_people/api_search');

        expect($output)->toMatchArray(['count' => 1, 'total' => 2])
            ->and($query)->toContain('person_titles[]=Sales Manager')->toContain('person_titles[]=VP')->toContain('person_locations[]=Austin')->toContain('revenue_range[min]=100')->toContain('per_page=100')
            ->and(apolloRequest('POST /mixed_people/api_search')->header('x-api-key')[0])->toBe('ap-key')
            ->and((new ApolloSearchPeopleNode)->effect([]))->toBe(ActionEffect::Read);
    });

    it('refuses a filter name that is not one', function () {
        fakeApollo([]);

        expect(fn () => (new ApolloSearchPeopleNode)->execute(apolloRun(), ['more_filters' => ['x&y=1' => 'z']], []))->toThrow(InvalidArgumentException::class);
    });

    it('enriches a person and a company, which spends credits, and asks for something to go on', function () {
        fakeApollo(['POST /people/match' => ['person' => ['id' => 'p1', 'email' => 'ada@acme.com']], 'GET /organizations/enrich' => ['organization' => ['id' => 'o1', 'name' => 'Acme']]]);

        $person = (new ApolloEnrichPersonNode)->execute(apolloRun(), ['email' => 'ada@acme.com'], []);
        $company = (new ApolloEnrichOrganizationNode)->execute(apolloRun(), ['domain' => 'acme.com'], []);

        expect($person['found'])->toBeTrue()
            ->and($company['organization']['name'])->toBe('Acme')
            ->and((new ApolloEnrichPersonNode)->effect([]))->toBe(ActionEffect::Write)
            ->and(fn () => (new ApolloEnrichPersonNode)->execute(apolloRun(), [], []))->toThrow(InvalidArgumentException::class);
    });

    it('bulk enriches at most ten people', function () {
        fakeApollo(['POST /people/bulk_match' => ['matches' => [['id' => 'p1']], 'missing_records' => 0, 'credits_consumed' => 1]]);

        $output = (new ApolloBulkEnrichPeopleNode)->execute(apolloRun(), ['details' => [['email' => 'a@acme.com']]], []);

        expect($output['credits_consumed'])->toBe(1)
            ->and(apolloBody('POST /people/bulk_match')['details'][0]['email'])->toBe('a@acme.com')
            ->and(fn () => (new ApolloBulkEnrichPeopleNode)->execute(apolloRun(), ['details' => array_fill(0, 11, ['email' => 'x'])], []))->toThrow(InvalidArgumentException::class, 'at most 10');
    });
});

describe('apollo records', function () {
    it('creates and updates contacts, and refuses an id that cannot sit in a URL', function () {
        fakeApollo(['POST /contacts' => ['contact' => ['id' => 'c1', 'email' => 'ada@acme.com']], 'PATCH /contacts/c1' => ['contact' => ['id' => 'c1', 'title' => 'CEO']]]);

        $created = (new ApolloCreateContactNode)->execute(apolloRun(), ['first_name' => 'Ada', 'email' => 'ada@acme.com', 'nonsense' => 'dropped'], []);
        $updated = (new ApolloUpdateContactNode)->execute(apolloRun(), ['contact_id' => 'c1', 'title' => 'CEO'], []);

        expect($created['contact']['id'])->toBe('c1')
            ->and(apolloBody('POST /contacts'))->toBe(['first_name' => 'Ada', 'email' => 'ada@acme.com'])
            ->and($updated['contact']['title'])->toBe('CEO')
            ->and(fn () => (new ApolloUpdateContactNode)->execute(apolloRun(), ['contact_id' => '../x'], []))->toThrow(InvalidArgumentException::class);
    });

    it('upserts with dedupe, and finds or creates by exact email', function () {
        fakeApollo(['POST /contacts' => ['contact' => ['id' => 'new']], 'POST /contacts/search' => ['contacts' => [['id' => 'other', 'email' => 'bob@acme.com'], ['id' => 'c1', 'email' => 'Ada@Acme.com']]]]);

        (new ApolloUpsertContactNode)->execute(apolloRun(), ['email' => 'ada@acme.com'], []);

        expect(apolloBody('POST /contacts')['run_dedupe'])->toBeTrue()
            ->and((new ApolloFindContactNode)->execute(apolloRun(), ['email' => 'ada@acme.com'], [])['contact']['id'])->toBe('c1')
            ->and((new ApolloFindContactNode)->execute(apolloRun(), ['email' => 'nobody@acme.com'], [])['found'])->toBeFalse()
            ->and((new ApolloFindOrCreateContactNode)->execute(apolloRun(), ['email' => 'ada@acme.com'], [])['created'])->toBeFalse()
            ->and((new ApolloFindOrCreateContactNode)->execute(apolloRun(), ['email' => 'nobody@acme.com'], [])['created'])->toBeTrue();
    });

    it('bulk creates contacts, and moves stages through the query', function () {
        fakeApollo(['POST /contacts/bulk_create' => ['created_contacts' => [['id' => 'c1']], 'existing_contacts' => [['id' => 'c2']]], 'POST /contacts/update_stages' => ['contacts' => [['id' => 'c1']]]]);

        $bulk = (new ApolloBulkCreateContactsNode)->execute(apolloRun(), ['contacts' => [['email' => 'a@acme.com']]], []);

        (new ApolloUpdateContactStagesNode)->execute(apolloRun(), ['contact_ids' => ['c1', 'c2'], 'contact_stage_id' => 's1'], []);

        expect($bulk['created_contacts'][0]['id'])->toBe('c1')->and($bulk['existing_contacts'][0]['id'])->toBe('c2')
            ->and(apolloQuery('POST /contacts/update_stages'))->toContain('contact_ids[]=c1')->toContain('contact_ids[]=c2')->toContain('contact_stage_id=s1');
    });

    it('creates accounts and deals, updates deals, and lists stages', function () {
        fakeApollo(['POST /accounts' => ['account' => ['id' => 'a1', 'name' => 'Acme']], 'POST /opportunities' => ['opportunity' => ['id' => 'd1', 'amount' => '500']], 'PATCH /opportunities/d1' => ['opportunity' => ['id' => 'd1', 'amount' => '900']], 'GET /opportunities/search' => ['opportunities' => [['id' => 'd1']], 'pagination' => ['total_entries' => 1]], 'GET /contact_stages' => ['contact_stages' => [['id' => 's1', 'name' => 'New']]]]);

        expect((new ApolloCreateAccountNode)->execute(apolloRun(), ['name' => 'Acme'], [])['account']['name'])->toBe('Acme')
            ->and((new ApolloCreateDealNode)->execute(apolloRun(), ['name' => 'Deal', 'amount' => '500'], [])['deal']['amount'])->toBe('500')
            ->and((new ApolloUpdateDealNode)->execute(apolloRun(), ['deal_id' => 'd1', 'amount' => '900'], [])['deal']['amount'])->toBe('900')
            ->and((new ApolloListDealsNode)->execute(apolloRun(), [], [])['total'])->toBe(1)
            ->and((new ApolloListContactStagesNode)->execute(apolloRun(), [], [])['contact_stages'][0]['name'])->toBe('New');
    });

    it('handles lists: the bare array of them, creating and adding', function () {
        fakeApollo(['GET /labels' => [['id' => 'l1', 'name' => 'Hot']], 'POST /labels' => ['label' => ['id' => 'l2']], 'POST /labels/add_entity_ids_to_label_names' => ['labels' => [['id' => 'l1']], 'message' => 'ok']]);

        expect((new ApolloListListsNode)->execute(apolloRun(), [], [])['lists'][0]['id'])->toBe('l1')
            ->and((new ApolloCreateListNode)->execute(apolloRun(), ['name' => 'Cold', 'modality' => 'contacts'], [])['list']['id'])->toBe('l2')
            ->and((new ApolloAddToListNode)->execute(apolloRun(), ['entity_ids' => ['c1'], 'label_names' => ['Hot'], 'modality' => 'contacts'], [])['message'])->toBe('ok')
            ->and(apolloBody('POST /labels/add_entity_ids_to_label_names'))->toMatchArray(['entity_ids' => ['c1'], 'label_names' => ['Hot']]);
    });
});

describe('apollo sequences, email, calls, tasks', function () {
    it('creates a sequence inactive, and says that starting one reaches people', function () {
        fakeApollo(['POST /sequences' => ['emailer_campaign' => ['id' => 'sq1']], 'POST /emailer_campaigns/sq1/approve' => ['emailer_campaign' => ['id' => 'sq1', 'active' => true]]]);

        $created = (new ApolloCreateSequenceNode)->execute(apolloRun(), ['name' => 'Intro'], []);
        $active = (new ApolloActivateSequenceNode)->execute(apolloRun(), ['sequence_id' => 'sq1'], []);

        expect($created['sequence']['id'])->toBe('sq1')
            ->and(apolloBody('POST /sequences')['active'])->toBeFalse()
            ->and($active['sequence']['active'])->toBeTrue()
            ->and((new ApolloActivateSequenceNode)->effect([]))->toBe(ActionEffect::External)
            ->and((new ApolloCreateSequenceNode)->effect([]))->toBe(ActionEffect::Write);
    });

    it('adds contacts to a sequence with the same id twice, as Apollo wants, and reports skips', function () {
        fakeApollo(['POST /emailer_campaigns/sq1/add_contact_ids' => ['contacts' => [['id' => 'c1']], 'skipped_contact_ids' => ['c2']]]);

        $output = (new ApolloAddContactsToSequenceNode)->execute(apolloRun(), ['sequence_id' => 'sq1', 'contact_ids' => ['c1', 'c2'], 'send_email_from_email_account_id' => 'e1'], []);

        expect($output['skipped_contact_ids'])->toBe(['c2'])
            ->and(apolloQuery('POST /emailer_campaigns/sq1/add_contact_ids'))->toContain('emailer_campaign_id=sq1')->toContain('contact_ids[]=c2')->toContain('send_email_from_email_account_id=e1')
            ->and((new ApolloAddContactsToSequenceNode)->effect([]))->toBe(ActionEffect::External);
    });

    it('changes contact status in sequences, and logs and searches calls', function () {
        fakeApollo(['POST /emailer_campaigns/remove_or_stop_contact_ids' => ['entity_progress_job' => null], 'POST /phone_calls' => ['phone_call' => ['id' => 'pc1']], 'GET /phone_calls/search' => ['phone_calls' => [['id' => 'pc1']]]]);

        (new ApolloUpdateSequenceContactStatusNode)->execute(apolloRun(), ['sequence_ids' => ['sq1'], 'contact_ids' => ['c1'], 'mode' => 'stop'], []);

        expect(apolloQuery('POST /emailer_campaigns/remove_or_stop_contact_ids'))->toContain('emailer_campaign_ids[]=sq1')->toContain('mode=stop')
            ->and((new ApolloCreateCallNode)->execute(apolloRun(), ['contact_id' => 'c1', 'to_number' => '+1555', 'user_ids' => ['u1']], [])['call']['id'])->toBe('pc1')
            ->and(apolloQuery('POST /phone_calls'))->toContain('user_id[]=u1')->toContain('contact_id=c1')
            ->and((new ApolloSearchCallsNode)->execute(apolloRun(), ['date_from' => '2026-01-01'], [])['phone_calls'][0]['id'])->toBe('pc1')
            ->and(apolloQuery('GET /phone_calls/search'))->toContain('date_range[min]=2026-01-01');
    });

    it('sends a draft, which is declared as reaching people', function () {
        fakeApollo(['POST /emailer_messages/m1/send_now' => ['emailer_message' => ['id' => 'm1', 'status' => 'sent']]]);

        expect((new ApolloSendEmailNode)->execute(apolloRun(), ['email_id' => 'm1'], [])['email']['status'])->toBe('sent')
            ->and((new ApolloSendEmailNode)->effect([]))->toBe(ActionEffect::External);
    });

    it('creates and completes tasks', function () {
        fakeApollo(['POST /tasks' => ['task' => ['id' => 't1', 'status' => 'scheduled']], 'POST /tasks/t1/complete' => ['task' => ['id' => 't1', 'status' => 'completed']]]);

        $created = (new ApolloCreateTaskNode)->execute(apolloRun(), ['user_id' => 'u1', 'contact_id' => 'c1', 'due_at' => '2026-12-01T09:00:00Z', 'type' => 'call'], []);

        expect($created['task']['id'])->toBe('t1')
            ->and(apolloBody('POST /tasks'))->toMatchArray(['type' => 'call', 'status' => 'scheduled', 'user_id' => 'u1'])
            ->and((new ApolloCompleteTaskNode)->execute(apolloRun(), ['task_id' => 't1'], [])['task']['status'])->toBe('completed');
    });
});

describe('apollo assistant, workspace and reports', function () {
    it('runs and polls an assistant task', function () {
        fakeApollo(['POST /agents/task' => ['task_id' => 'x1', 'status' => 'running'], 'POST /agents/poll_task' => ['status' => 'complete', 'messages' => []]]);

        expect((new ApolloRunAssistantTaskNode)->execute(apolloRun(), ['instruction' => 'Find leads'], [])['task_id'])->toBe('x1')
            ->and((new ApolloPollAssistantTaskNode)->execute(apolloRun(), ['task_id' => 'x1'], [])['status'])->toBe('complete')
            ->and((new ApolloRunAssistantTaskNode)->effect([]))->toBe(ActionEffect::External);
    });

    it('reads the current user, adds a custom field, exports and queries a report', function () {
        fakeApollo(['GET /users/api_profile' => ['id' => 'u1', 'email' => 'me@acme.com'], 'POST /fields' => ['typed_custom_fields' => [['id' => 'f1']]], 'POST /conversations/export' => ['export_id' => 'ex1'], 'POST /reports/sync_report' => ['response' => ['rows' => []]]]);

        expect((new ApolloGetCurrentUserNode)->execute(apolloRun(), [], [])['email'])->toBe('me@acme.com')
            ->and(apolloQuery('GET /users/api_profile'))->toContain('include_credit_usage=true')
            ->and((new ApolloCreateCustomFieldNode)->execute(apolloRun(), ['label' => 'Tier', 'modality' => 'contact', 'type' => 'string'], [])['typed_custom_fields'][0]['id'])->toBe('f1')
            ->and((new ApolloExportConversationsNode)->execute(apolloRun(), ['start_time' => '2026-01-01T00:00:00Z', 'end_time' => '2026-02-01T00:00:00Z', 'email' => 'me@acme.com'], [])['export_id'])->toBe('ex1')
            ->and((new ApolloQueryReportNode)->execute(apolloRun(), ['metrics' => [['value' => 'emails_sent']], 'date_ranges' => [['modality' => 'last_7_days']]], [])['response']['rows'])->toBe([])
            ->and(apolloBody('POST /reports/sync_report'))->toMatchArray(['group_by' => [], 'sorts' => [], 'group_by_totals_selected' => false]);
    });

    it('explains errors with a hint, preferring error_details', function (int $status, array $body, string $expected) {
        fakeApollo(['GET /contact_stages' => fn () => Http::response($body, $status)]);

        expect(fn () => (new ApolloListContactStagesNode)->execute(apolloRun(), [], []))->toThrow(RuntimeException::class, $expected);
    })->with([
        'bad key' => [401, ['error' => 'Invalid access credentials.'], 'Reconnect Apollo'],
        'not master' => [403, ['error_details' => ['message' => 'Master key required']], 'master API key'],
        'rate limited' => [429, ['message' => 'Too many calls'], 'rate limiting'],
    ]);
});

describe('apollo options', function () {
    function apolloOptionsQuery(string $search = '', ?string $cursor = null): NodeOptionsQuery
    {
        return new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), [], 'ap-key', $search, $cursor);
    }

    it('lists stages filtered here, and contacts and accounts searched by Apollo', function () {
        fakeApollo(['GET /contact_stages' => ['contact_stages' => [['id' => 's1', 'name' => 'New'], ['id' => 's2', 'name' => 'Won']]], 'POST /contacts/search' => ['contacts' => [['id' => 'c1', 'first_name' => 'Ada', 'last_name' => 'Lee', 'email' => 'ada@acme.com']]], 'GET /labels' => [['id' => 'l1', 'name' => 'Hot']]]);

        expect(app(ApolloOptions::class)->load('apollo.contact_stages', apolloOptionsQuery('wo'))->toArray()['options'])->toBe([['value' => 's2', 'label' => 'Won']])
            ->and(app(ApolloOptions::class)->load('apollo.contacts', apolloOptionsQuery('ada'))->toArray()['options'])->toBe([['value' => 'c1', 'label' => 'Ada Lee', 'description' => 'ada@acme.com']])
            ->and(apolloBody('POST /contacts/search')['q_keywords'])->toBe('ada')
            ->and(app(ApolloOptions::class)->load('apollo.lists', apolloOptionsQuery())->toArray()['options'][0]['label'])->toBe('Hot');
    });

    it('offers a next page when a page is full', function () {
        fakeApollo(['GET /users/search' => ['users' => array_map(fn (int $n): array => ['id' => "u{$n}", 'name' => "U{$n}"], range(1, 50))]]);

        expect(app(ApolloOptions::class)->load('apollo.users', apolloOptionsQuery())->toArray()['next_cursor'])->toBe('2');
    });
});

describe('apollo triggers', function () {
    it('reads new contacts sorted by creation, and only those in the window', function () {
        fakeApollo(['POST /contacts/search' => ['contacts' => [['id' => 'c2', 'created_at' => now()->subMinutes(5)->toIso8601String()], ['id' => 'c1', 'created_at' => now()->subDays(40)->toIso8601String()]]]]);

        $output = (new ApolloListNewContactsNode)->execute(apolloRun(), [], []);

        expect(array_column($output['records'], 'id'))->toBe(['c2'])
            ->and(apolloBody('POST /contacts/search'))->toMatchArray(['sort_by_field' => 'contact_created_at', 'sort_ascending' => false]);
    });

    it('pages through deals because Apollo cannot sort them by time, and tells a change from a creation', function () {
        $full = array_map(fn (int $n): array => ['id' => "d{$n}", 'created_at' => now()->subDays(100)->toIso8601String(), 'updated_at' => now()->subDays(100)->toIso8601String()], range(1, 100));
        fakeApollo(['GET /opportunities/search' => function (Request $request) use ($full) {
            return str_contains($request->url(), '?page=1&') ? Http::response(['opportunities' => $full]) : Http::response(['opportunities' => [['id' => 'd200', 'created_at' => now()->subMinute()->toIso8601String(), 'updated_at' => now()->subMinute()->toIso8601String()]]]);
        }]);

        $new = (new ApolloListNewDealsNode)->execute(apolloRun(), [], []);
        $node = new ApolloListChangedDealsNode;

        expect(array_column($new['records'], 'id'))->toBe(['d200'])
            ->and($node->triggerItemId(['id' => 'd1', 'updated_at' => 'T1']))->toBe('d1:T1')
            ->and((new ApolloListNewDealsNode)->triggerItemId(['id' => 'd1', 'updated_at' => 'T1']))->toBe('d1')
            ->and($node->triggerName())->toBe('Apollo: Deal updated');
    });

    it('counts an email reaching another status as a new event, and a task once', function () {
        expect((new ApolloSearchEmailsNode)->triggerItemId(['id' => 'm1', 'status' => 'opened']))->toBe('m1:opened')
            ->and((new ApolloSearchTasksNode)->triggerItemId(['id' => 't1']))->toBe('t1');
    });

    it('polls new contacts: a baseline first, then one run for each new one', function () {
        Queue::fake([FireTriggerEvent::class]);
        fakeApollo([]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'apollo') ?? Connector::factory()->create(['key' => 'apollo']))
            ->create(['data' => ['api_key' => 'ap-key'], 'created_by' => $owner->id]);
        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'apollo_list_new_contacts', 'config' => ['credential_id' => $credential->id, 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);
        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        SyncTriggerSubscription::dispatchSync($trigger->id);
        $poll = fn (array $ids) => fakeApollo(['POST /contacts/search' => ['contacts' => array_map(fn (string $id): array => ['id' => $id, 'created_at' => now()->toIso8601String()], $ids)]]);

        $poll(['c1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['c3', 'c2', 'c1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['c2', 'c3']);
    });
});

it('makes any API call on the apollo host, and refuses a path that leaves it', function () {
    fakeApollo(['POST /contacts/search' => ['contacts' => []]]);

    $node = app(NodeRegistry::class)->resolve('apollo_api_request');
    $run = apolloRun();
    $output = $node->execute($run, ['method' => 'POST', 'path' => '/contacts/search', 'body' => ['q_keywords' => 'ada']], []);

    expect($output)->toBe(['status' => 200, 'body' => ['contacts' => []]])
        ->and(apolloBody('POST /contacts/search'))->toBe(['q_keywords' => 'ada'])
        ->and($node->effect([])->value)->toBe('external');

    foreach (['https://evil.example/x', '/../x', '/a?b=1'] as $path) {
        expect(fn () => $node->execute($run, ['method' => 'GET', 'path' => $path], []))->toThrow(InvalidArgumentException::class);
    }
});
