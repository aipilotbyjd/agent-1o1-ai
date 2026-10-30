<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Nodes\NodeCategory;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Semrush\AbstractSemrushNode;
use App\Nodes\Integrations\Semrush\SemrushAddTrackingKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushBacklinksBatchComparisonNode;
use App\Nodes\Integrations\Semrush\SemrushCompareDomainsNode;
use App\Nodes\Integrations\Semrush\SemrushCreateSiteAuditCampaignNode;
use App\Nodes\Integrations\Semrush\SemrushDomainOrganicKeywordsNode;
use App\Nodes\Integrations\Semrush\SemrushDomainOverviewNode;
use App\Nodes\Integrations\Semrush\SemrushGetApiUnitsNode;
use App\Nodes\Integrations\Semrush\SemrushGetProjectNode;
use App\Nodes\Integrations\Semrush\SemrushGetSiteAuditNode;
use App\Nodes\Integrations\Semrush\SemrushListLostBacklinksNode;
use App\Nodes\Integrations\Semrush\SemrushRunSiteAuditNode;
use App\Nodes\Integrations\Semrush\SemrushTrackingOrganicPositionsNode;
use App\Nodes\Integrations\Semrush\SemrushTrafficSummaryNode;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\SemrushOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Database\Seeders\AppNodeCategorySeeder;
use Database\Seeders\ConnectorSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function semrushRun(): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'semrush') ?? Connector::factory()->create(['key' => 'semrush', 'name' => 'Semrush']))->id,
        'data' => ['api_key' => 'sem-secret'],
        'is_default' => true,
    ]);

    return $run;
}

/**
 * @param  array<string, mixed>  $routes  path => answer (a closure may look at the request)
 */
function semrushFake(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['*semrush.com*' => function (Request $request) use ($routes) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $answer = $routes[$request->method().' '.$path] ?? $routes[$path] ?? null;

        if ($answer === null) {
            return Http::response(['message' => "Nothing fakes {$path}"], 404);
        }

        $answer = value($answer, $request);

        return $answer instanceof PromiseInterface ? $answer : Http::response($answer);
    }]);
}

/**
 * @return array<string, mixed>
 */
function semrushQuery(Request $request): array
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query;
}

function semrushLastRequest(): Request
{
    $recorded = Http::recorded()->all();

    return end($recorded)[0];
}

describe('semrush nodes', function () {
    it('registers every node with the connector and a seeded category', function () {
        $this->seed([ConnectorSeeder::class, AppNodeCategorySeeder::class]);

        $types = array_values(array_filter(array_column(app(NodeRegistry::class)->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'semrush_')));

        expect(count($types))->toBeGreaterThanOrEqual(120);

        foreach ($types as $type) {
            expect(app(NodeRegistry::class)->resolve($type))->toBeInstanceOf(AbstractSemrushNode::class)
                ->and(app(NodeRegistry::class)->resolve($type)->category())->toBe('semrush');
        }

        expect(Connector::query()->where('key', 'semrush')->first()->sort_order)->toBe(56)
            ->and(NodeCategory::query()->where('slug', 'semrush')->first()->sort_order)->toBe(56);
    });

    it('runs every node against its endpoint', function () {
        $samples = ['method' => 'GET', 'path' => '/management/v1/projects', 'domain' => 'example.com', 'database' => 'us', 'url' => 'https://example.com/a', 'phrase' => 'seo', 'subdomain' => 'blog.example.com', 'subfolder' => 'example.com/blog/', 'target' => 'example.com', 'target_type' => 'root_domain', 'targets' => ['a.com', 'b.com'], 'selected_targets' => ['a.com'], 'domains' => ['a.com', 'b.com'], 'project_id' => '123', 'campaign_id' => '123_456', 'snapshot_id' => 'abc123', 'issue_id' => 5, 'page_id' => 'abc', 'keywords' => ['seo'], 'tags' => ['t'], 'competitors' => ['a.com'], 'tracking_url' => 'example.com', 'tracking_url_type' => 'rootdomain', 'location_id' => 2840, 'name' => 'Site', 'start_crawl_url' => 'example.com', 'pages_limit' => 10, 'page_limit' => 10, 'project_name' => 'Site', 'category' => 'Arts', 'device_type' => 'desktop'];

        Http::fake(['*semrush.com*' => function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_contains($path, '/analytics/ta/limits') || str_contains($path, 'management') || str_contains($path, '/reports/v1')) {
                return Http::response(['project_id' => 123, 'ok' => true]);
            }

            return Http::response("Keyword;Search Volume\nseo;1000\n");
        }]);

        $run = semrushRun();
        $ran = 0;

        foreach (app(NodeRegistry::class)->placeableTypes() as $described) {
            if (! str_starts_with($described['type'], 'semrush_')) {
                continue;
            }

            $node = app(NodeRegistry::class)->resolve($described['type']);
            $config = array_intersect_key($samples, array_flip($node->configSchema()['required']));

            expect(array_diff($node->configSchema()['required'], array_keys($config)))->toBe([], "{$described['type']} has a required setting the sweep doesn't know");
            expect($node->execute($run, $config, []))->toBeArray();
            $ran++;
        }

        expect($ran)->toBeGreaterThanOrEqual(120);
    });

    it('parses the semicolon-separated reports into rows, quoting and all', function () {
        semrushFake(['/' => "Keyword;Position;Traffic (%);Url\n\"seo; tools\";3;1.5;https://example.com/a\nbuy shoes;7;0.2;https://example.com/b\n"]);

        $output = (new SemrushDomainOrganicKeywordsNode)->execute(semrushRun(), ['domain' => 'https://Example.com/', 'database' => 'us', 'limit' => 2], []);

        expect($output['count'])->toBe(2)
            ->and($output['rows'][0])->toBe(['keyword' => 'seo; tools', 'position' => '3', 'traffic' => '1.5', 'url' => 'https://example.com/a']);

        $query = semrushQuery(semrushLastRequest());

        expect($query)->toMatchArray(['type' => 'domain_organic', 'domain' => 'Example.com', 'database' => 'us', 'display_limit' => '2', 'export_escape' => '1', 'key' => 'sem-secret'])
            ->and($query['export_columns'])->toBe('Ph,Po,Pp,Pd,Nq,Cp,Ur,Tr,Tc,Co,Nr,Td');
    });

    it('reads a report with nothing in it as empty, not as a failure', function () {
        semrushFake(['/' => 'ERROR 50 :: NOTHING FOUND']);

        expect((new SemrushDomainOverviewNode)->execute(semrushRun(), ['domain' => 'nothing.example', 'database' => 'us'], []))->toBe(['rows' => [], 'count' => 0]);
    });

    it('explains errors, with a hint, wherever they arrive', function () {
        semrushFake(['/' => 'ERROR 120 :: WRONG KEY - ID PAIR']);
        expect(fn () => (new SemrushDomainOverviewNode)->execute(semrushRun(), ['domain' => 'a.com', 'database' => 'us'], []))->toThrow(RuntimeException::class, 'Reconnect Semrush');

        semrushFake(['/' => 'ERROR 132 :: API UNITS BALANCE IS ZERO']);
        expect(fn () => (new SemrushDomainOverviewNode)->execute(semrushRun(), ['domain' => 'a.com', 'database' => 'us'], []))->toThrow(RuntimeException::class, 'run out of API units');

        semrushFake(['/management/v1/projects/9' => fn () => Http::response(['message' => 'Nope'], 403)]);
        expect(fn () => (new SemrushGetProjectNode)->execute(semrushRun(), ['project_id' => '9'], []))->toThrow(RuntimeException::class, 'does not include this');

        semrushFake(['/' => fn () => Http::response('slow down', 429)]);
        expect(fn () => (new SemrushDomainOverviewNode)->execute(semrushRun(), ['domain' => 'a.com', 'database' => 'us'], []))->toThrow(RuntimeException::class, 'rate limiting');
    });

    it('never puts the key in the message of a dropped connection', function () {
        Http::fake(fn () => throw new ConnectionException('cURL error 28 for https://api.semrush.com/?key=sem-secret'));

        try {
            (new SemrushDomainOverviewNode)->execute(semrushRun(), ['domain' => 'a.com', 'database' => 'us'], []);
            $this->fail('It should have thrown.');
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())->not->toContain('sem-secret');
        }
    });

    it('sends several targets the way Semrush documents them', function () {
        semrushFake(['/analytics/v1/' => "target;ascore\na.com;50\n"]);

        (new SemrushBacklinksBatchComparisonNode)->execute(semrushRun(), ['targets' => ['a.com', 'b.com'], 'target_type' => 'root_domain'], []);

        $url = rawurldecode(semrushLastRequest()->url());

        expect($url)->toContain('targets[]=a.com&targets[]=b.com')->toContain('target_types[]=root_domain&target_types[]=root_domain');
    });

    it('writes the domains of a comparison in Semrush\'s form', function () {
        semrushFake(['/' => "Keyword;P0\nseo;1\n"]);

        (new SemrushCompareDomainsNode)->execute(semrushRun(), ['domains' => ['https://me.com', 'them.com'], 'mode' => 'untapped', 'database' => 'us'], []);

        expect(semrushQuery(semrushLastRequest())['domains'])->toBe('*|or|them.com|-|or|me.com')
            ->and(SemrushCompareDomainsNode::comparison(['me.com', 'a.com', 'b.com'], 'shared', 'ad'))->toBe('*|ad|me.com|*|ad|a.com|*|ad|b.com');
    });

    it('reads a Trends report', function () {
        semrushFake(['/analytics/ta/api/v3/summary' => "target;visits;users\ngolang.org;4491179;1400453\n"]);

        $output = (new SemrushTrafficSummaryNode)->execute(semrushRun(), ['targets' => ['golang.org', 'go.dev'], 'country' => 'US'], []);

        expect($output['rows'][0])->toBe(['target' => 'golang.org', 'visits' => '4491179', 'users' => '1400453'])
            ->and(semrushQuery(semrushLastRequest()))->toMatchArray(['targets' => 'golang.org,go.dev', 'country' => 'US']);
    });

    it('reads the balance of API units', function () {
        Http::fake(['https://www.semrush.com/users/countapiunits.html*' => Http::response('1,000')]);

        expect((new SemrushGetApiUnitsNode)->execute(semrushRun(), [], []))->toBe(['api_units' => 1000]);
    });

    it('manages projects and refuses an id that is not one', function () {
        semrushFake(['/management/v1/projects/42' => ['project_id' => 42, 'project_name' => 'Site', 'url' => 'a.com']]);

        expect((new SemrushGetProjectNode)->execute(semrushRun(), ['project_id' => '42'], []))->toMatchArray(['project_name' => 'Site'])
            ->and(fn () => (new SemrushGetProjectNode)->execute(semrushRun(), ['project_id' => '../9'], []))->toThrow(InvalidArgumentException::class);
    });

    it('creates a project and its site audit in one step', function () {
        semrushFake(['POST /management/v1/projects' => ['project_id' => 7, 'project_name' => 'Site', 'url' => 'a.com'], 'POST /management/v1/projects/7/siteaudit/enable' => ['ok' => true]]);

        $output = (new SemrushCreateSiteAuditCampaignNode)->execute(semrushRun(), ['name' => 'Site', 'start_crawl_url' => 'https://a.com/', 'pages_limit' => 100], []);

        expect($output)->toMatchArray(['project_id' => 7, 'site_audit_enabled' => true])
            ->and(json_decode(semrushLastRequest()->body(), true))->toBe(['domain' => 'a.com', 'pageLimit' => 100]);
    });

    it('runs an audit', function () {
        semrushFake(['POST /reports/v1/projects/7/siteaudit/launch' => ['snapshot_id' => 'abc']]);

        expect((new SemrushRunSiteAuditNode)->execute(semrushRun(), ['project_id' => '7'], []))->toBe(['snapshot_id' => 'abc']);
    });

    it('adds tracking keywords with their tags, and checks the campaign id', function () {
        semrushFake(['PUT /management/v1/projects/7_8/keywords' => ['campaign_id' => '7_8']]);

        (new SemrushAddTrackingKeywordsNode)->execute(semrushRun(), ['campaign_id' => '7_8', 'keywords' => ['seo'], 'tags' => ['a']], []);

        expect(json_decode(semrushLastRequest()->body(), true))->toBe(['keywords' => [['keyword' => 'seo', 'tags' => ['a']]]])
            ->and(fn () => (new SemrushAddTrackingKeywordsNode)->execute(semrushRun(), ['campaign_id' => '7/8', 'keywords' => ['seo']], []))->toThrow(InvalidArgumentException::class);
    });

    it('reads a tracking report into rows', function () {
        semrushFake(['/reports/v1/projects/7_8/tracking/' => ['total' => '2', 'data' => ['0' => ['Ph' => 'seo'], '1' => ['Ph' => 'tools']]]]);

        $output = (new SemrushTrackingOrganicPositionsNode)->execute(semrushRun(), ['campaign_id' => '7_8', 'display_limit' => 2], []);

        expect($output['rows'])->toBe([['Ph' => 'seo'], ['Ph' => 'tools']])
            ->and($output['total'])->toBe('2')
            ->and(semrushQuery(semrushLastRequest()))->toMatchArray(['action' => 'report', 'type' => 'tracking_position_organic', 'display_limit' => '2']);
    });
});

describe('semrush triggers', function () {
    it('offers the triggers in the catalog', function () {
        $names = collect(app(NodeRegistry::class)->triggerCatalog())->where('category', 'semrush')->pluck('name');

        expect($names)->toContain('Semrush: New backlink', 'Semrush: Lost backlink', 'Semrush: New referring domain', 'Semrush: New organic keyword', 'Semrush: Lost organic keyword', 'Semrush: Completed site audit', 'Semrush: New site audit issue');
    });

    it('keeps only the links marked lost', function () {
        semrushFake(['/analytics/v1/' => "source_url;target_url;lostlink\nhttps://a.com;https://me.com;true\nhttps://b.com;https://me.com;false\n"]);

        $output = (new SemrushListLostBacklinksNode)->execute(semrushRun(), ['target' => 'me.com', 'target_type' => 'root_domain'], []);

        expect(array_column($output['rows'], 'source_url'))->toBe(['https://a.com'])
            ->and(semrushQuery(semrushLastRequest())['display_sort'])->toBe('last_seen_desc');
    });

    it('lists audit issues by id from the summary', function () {
        semrushFake(['/reports/v1/projects/7/siteaudit/info' => ['errors' => 2, 'defects' => ['8' => 3, '14' => 1]]]);

        expect((new SemrushGetSiteAuditNode)->execute(semrushRun(), ['project_id' => '7'], [])['issues'])->toBe([['id' => '8', 'issue_id' => 8, 'count' => 3], ['id' => '14', 'issue_id' => 14, 'count' => 1]]);
    });

    it('polls: a baseline first, then one run for each new item', function (string $type, array $nodeConfig, string $path, callable $answer, array $first, array $second, string $payloadKey, array $expected) {
        Queue::fake([FireTriggerEvent::class]);
        semrushFake([]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'semrush') ?? Connector::factory()->create(['key' => 'semrush']))
            ->create(['data' => ['api_key' => 'sem-secret'], 'created_by' => $owner->id]);
        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => $type, 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);
        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        SyncTriggerSubscription::dispatchSync($trigger->id);

        semrushFake([$path => $answer($first)]);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        semrushFake([$path => $answer($second)]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck("payload.{$payloadKey}")->sort()->values()->all())->toBe($expected);
    })->with([
        'new backlink' => ['semrush_list_new_backlinks', ['target' => 'me.com', 'target_type' => 'root_domain'], '/analytics/v1/', fn (array $sources) => "source_url;target_url\n".implode("\n", array_map(fn (string $s): string => "{$s};https://me.com", $sources))."\n", ['https://a.com'], ['https://b.com', 'https://a.com'], 'source_url', ['https://b.com']],
        'lost backlink' => ['semrush_list_lost_backlinks', ['target' => 'me.com', 'target_type' => 'root_domain'], '/analytics/v1/', fn (array $sources) => "source_url;target_url;lostlink\n".implode("\n", array_map(fn (string $s): string => "{$s};https://me.com;true", $sources))."\n", ['https://a.com'], ['https://b.com', 'https://a.com'], 'source_url', ['https://b.com']],
        'new referring domain' => ['semrush_list_new_referring_domains', ['target' => 'me.com', 'target_type' => 'root_domain'], '/analytics/v1/', fn (array $domains) => "domain;backlinks_num\n".implode("\n", array_map(fn (string $s): string => "{$s};3", $domains))."\n", ['a.com'], ['b.com', 'a.com'], 'domain', ['b.com']],
        'new keyword' => ['semrush_list_new_keywords', ['domain' => 'me.com', 'database' => 'us'], '/', fn (array $words) => "Keyword;Position\n".implode("\n", array_map(fn (string $s): string => "{$s};3", $words))."\n", ['seo'], ['tools', 'seo'], 'keyword', ['tools']],
        'lost keyword' => ['semrush_list_lost_keywords', ['domain' => 'me.com', 'database' => 'us'], '/', fn (array $words) => "Keyword;Position\n".implode("\n", array_map(fn (string $s): string => "{$s};0", $words))."\n", ['seo'], ['tools', 'seo'], 'keyword', ['tools']],
        'completed audit' => ['semrush_list_site_audit_snapshots', ['project_id' => '7'], '/reports/v1/projects/7/siteaudit/snapshots', fn (array $ids) => ['snapshots' => array_map(fn (string $id): array => ['snapshot_id' => $id, 'finish_date' => 1410178856809], $ids)], ['s1'], ['s1', 's2'], 'snapshot_id', ['s2']],
        'new audit issue' => ['semrush_get_site_audit', ['project_id' => '7'], '/reports/v1/projects/7/siteaudit/info', fn (array $ids) => ['defects' => array_fill_keys($ids, 2)], ['8'], ['8', '14'], 'issue_id', [14]],
    ]);
});

describe('semrush options', function () {
    it('lists projects, campaigns and finished audits', function () {
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = fn (array $config = [], ?string $search = null) => new NodeOptionsQuery($workspace, User::factory()->create(), $config, 'sem-secret', $search);

        semrushFake(['/management/v1/projects' => [['project_id' => 7, 'project_name' => 'Site', 'url' => 'a.com'], ['project_id' => 8, 'project_name' => 'Other', 'url' => 'b.com']]]);
        expect(app(SemrushOptions::class)->load('semrush.projects', $query(search: 'site'))->toArray()['options'])->toBe([['value' => '7', 'label' => 'Site', 'description' => 'a.com']])
            ->and(semrushQuery(semrushLastRequest())['key'])->toBe('sem-secret');

        semrushFake(['/management/v1/projects/7/tracking/campaigns' => [['id' => '7_8', 'url' => 'a.com', 'device' => 'desktop', 'location' => ['name' => 'US']]]]);
        expect(app(SemrushOptions::class)->load('semrush.campaigns', $query(['project_id' => '7']))->toArray()['options'])->toBe([['value' => '7_8', 'label' => 'a.com', 'description' => 'US desktop']]);

        semrushFake(['/reports/v1/projects/7/siteaudit/snapshots' => ['snapshots' => [['snapshot_id' => 'abc', 'finish_date' => 1410178856809]]]]);
        expect(app(SemrushOptions::class)->load('semrush.snapshots', $query(['project_id' => '7']))->toArray()['options'][0]['value'])->toBe('abc');
    });
});

describe('semrush connector', function () {
    function semrushCredential(): ConnectorCredential
    {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

        return ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'semrush') ?? Connector::factory()->create(['key' => 'semrush', 'name' => 'Semrush']))
            ->create(['data' => ['api_key' => 'sem-secret'], 'created_by' => $owner->id]);
    }

    it('checks the key against the units balance', function () {
        Http::fake(['https://www.semrush.com/users/countapiunits.html*' => Http::response('1,000')]);

        expect(app(ConnectorCredentialTester::class)->test(semrushCredential()))->toMatchArray(['ok' => true, 'account' => '1,000 API units left']);
    });

    it('fails the check when the balance is an error line', function () {
        Http::fake(['https://www.semrush.com/users/countapiunits.html*' => Http::response('ERROR 120 :: WRONG KEY - ID PAIR')]);

        expect(app(ConnectorCredentialTester::class)->test(semrushCredential()))->toMatchArray(['ok' => false]);
    });
});

it('makes any API call on the semrush host, and refuses a path that leaves it', function () {
    semrushFake(['GET /management/v1/projects' => ['projects' => []]]);

    $node = app(NodeRegistry::class)->resolve('semrush_api_request');
    $run = semrushRun();
    $output = $node->execute($run, ['method' => 'GET', 'path' => '/management/v1/projects', 'query' => ['page' => 2]], []);

    expect($output)->toBe(['status' => 200, 'body' => ['projects' => []]])
        ->and(semrushQuery(semrushLastRequest()))->toMatchArray(['page' => '2'])
        ->and($node->effect([])->value)->toBe('external');

    foreach (['https://evil.example/x', '/../x', '/a?key=1'] as $path) {
        expect(fn () => $node->execute($run, ['method' => 'GET', 'path' => $path], []))->toThrow(InvalidArgumentException::class);
    }

    expect(fn () => $node->execute($run, ['method' => 'TRACE', 'path' => '/a'], []))->toThrow(InvalidArgumentException::class);
});
