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
use App\Nodes\Integrations\Ahrefs\AbstractAhrefsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsAddProjectKeywordsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBacklinksNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBatchAnalysisNode;
use App\Nodes\Integrations\Ahrefs\AhrefsBrandRadarAiResponsesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsContentGapNode;
use App\Nodes\Integrations\Ahrefs\AhrefsDeleteProjectsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsDomainRatingNode;
use App\Nodes\Integrations\Ahrefs\AhrefsGscKeywordsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsKeywordsOverviewNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListLostReferringDomainsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsListRankChangesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsMetricsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsRankTrackerOverviewNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSerpOverviewNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSiteAuditIssuesNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSiteAuditProjectsNode;
use App\Nodes\Integrations\Ahrefs\AhrefsSocialCreatePostNode;
use App\Nodes\Integrations\Ahrefs\AhrefsWebAnalyticsBreakdownNode;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\AhrefsOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Database\Seeders\AppNodeCategorySeeder;
use Database\Seeders\ConnectorSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function ahrefsRun(): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'ahrefs') ?? Connector::factory()->create(['key' => 'ahrefs', 'name' => 'Ahrefs']))->id,
        'data' => ['api_key' => 'ah-secret'],
        'is_default' => true,
    ]);

    return $run;
}

/**
 * @param  array<string, mixed>  $routes  path under /v3 => answer
 */
function ahrefsFake(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.ahrefs.com/*' => function (Request $request) use ($routes) {
        $path = (string) preg_replace('~^/v3~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $answer = $routes[$request->method().' '.$path] ?? $routes[$path] ?? null;

        if ($answer === null) {
            return Http::response(['error' => "Nothing fakes {$path}"], 404);
        }

        $answer = value($answer, $request);

        return $answer instanceof PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function ahrefsLastRequest(): Request
{
    $recorded = Http::recorded()->all();

    return end($recorded)[0];
}

/**
 * @return array<string, mixed>
 */
function ahrefsQuery(Request $request): array
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query;
}

describe('ahrefs nodes', function () {
    it('registers every node with the connector and a seeded category', function () {
        $this->seed([ConnectorSeeder::class, AppNodeCategorySeeder::class]);

        $types = array_values(array_filter(array_column(app(NodeRegistry::class)->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'ahrefs_')));

        expect(count($types))->toBeGreaterThanOrEqual(120);

        foreach ($types as $type) {
            expect(app(NodeRegistry::class)->resolve($type))->toBeInstanceOf(AbstractAhrefsNode::class);
        }

        expect(Connector::query()->where('key', 'ahrefs')->first()->sort_order)->toBe(57)
            ->and(NodeCategory::query()->where('slug', 'ahrefs')->first()->sort_order)->toBe(57);
    });

    it('runs every node against the path of its documented endpoint', function () {
        $samples = ['method' => 'GET', 'path' => '/subscription-info/limits-and-usage', 'target' => 'example.com', 'project_id' => '123', 'keyword_list_id' => '45', 'report_id' => 'r1', 'keyword' => 'seo', 'keywords' => 'seo', 'country' => 'us', 'device' => 'desktop', 'date_from' => '2026-01-01', 'date_to' => '2026-02-01', 'from' => '2026-01-01', 'to' => '2026-02-01', 'data_source' => ['chatgpt'], 'targets' => [['url' => 'a.com', 'mode' => 'domain', 'protocol' => 'both']], 'competitors' => [['url' => 'a.com', 'mode' => 'domain']], 'locations' => [['country' => 'us', 'language' => 'en']], 'project_name' => 'Site', 'mode' => 'domain', 'url' => 'a.com', 'protocol' => 'both', 'project_ids' => '1,2', 'keyword_list_ids' => '1', 'tags' => ['a'], 'name' => 'List', 'location' => 'all', 'country_code' => 'us', 'prompts' => ['Who?'], 'countries' => ['us'], 'granularity' => 'daily', 'dimension' => 'cities', 'target_url' => 'https://a.com/', 'prompts_frequency' => [['data_source' => 'chatgpt', 'frequency' => 'daily']], 'post_id' => '1', 'channel_id' => '2', 'external_post_id' => '3', 'status' => 'published', 'channel_ids' => ['2'], 'text_content' => 'Hi', 'timing' => 'now', 'scheduled_at' => '2026-01-01'];

        Http::fake(['api.ahrefs.com/*' => Http::response(['rows' => [], 'stats' => [], 'points' => []])]);

        $run = ahrefsRun();
        $ran = 0;

        foreach (app(NodeRegistry::class)->placeableTypes() as $described) {
            if (! str_starts_with($described['type'], 'ahrefs_')) {
                continue;
            }

            $node = app(NodeRegistry::class)->resolve($described['type']);
            $required = $node->configSchema()['required'];
            $config = array_intersect_key($samples, array_flip($required));

            foreach ($config as $key => $value) {
                $type = $node->configSchema()['properties'][$key]['type'] ?? null;

                if ($type === 'array' && ! is_array($value)) {
                    $config[$key] = [$value];
                }

                if ($type === 'object' && ! is_array($value)) {
                    $config[$key] = ['url' => $value, 'mode' => 'domain', 'protocol' => 'both'];
                }
            }

            expect(array_diff($required, array_keys($config)))->toBe([], "{$described['type']} has a required setting the sweep doesn't know");
            expect($node->execute($run, $config, []))->toBeArray();
            expect(ahrefsLastRequest()->hasHeader('Authorization', 'Bearer ah-secret'))->toBeTrue();
            $ran++;
        }

        expect($ran)->toBeGreaterThanOrEqual(120);
    });

    it('reads rows with a count, usual columns and a small default limit', function () {
        ahrefsFake(['/site-explorer/all-backlinks' => ['backlinks' => [['url_from' => 'https://a.com', 'url_to' => 'https://me.com']]]]);

        $output = (new AhrefsBacklinksNode)->execute(ahrefsRun(), ['target' => 'me.com', 'mode' => 'domain'], []);
        $query = ahrefsQuery(ahrefsLastRequest());

        expect($output)->toBe(['backlinks' => [['url_from' => 'https://a.com', 'url_to' => 'https://me.com']], 'count' => 1])
            ->and($query)->toMatchArray(['target' => 'me.com', 'mode' => 'domain', 'limit' => '100', 'output' => 'json'])
            ->and($query['select'])->toContain('url_from', 'url_to');
    });

    it('reads a single object as the output, and uses today for a report that needs a date', function () {
        ahrefsFake(['/site-explorer/domain-rating' => ['domain_rating' => ['domain_rating' => 72.5, 'ahrefs_rank' => 1234]]]);

        expect((new AhrefsDomainRatingNode)->execute(ahrefsRun(), ['target' => 'me.com'], []))->toBe(['domain_rating' => 72.5, 'ahrefs_rank' => 1234])
            ->and(ahrefsQuery(ahrefsLastRequest())['date'])->toBe(now()->utc()->toDateString());
    });

    it('honours the columns the member chose', function () {
        ahrefsFake(['/site-explorer/metrics' => ['metrics' => ['org_traffic' => 10]]]);

        (new AhrefsMetricsNode)->execute(ahrefsRun(), ['target' => 'me.com', 'date' => '2026-01-01'], []);
        expect(ahrefsQuery(ahrefsLastRequest())['date'])->toBe('2026-01-01');

        ahrefsFake(['/keywords-explorer/overview' => ['keywords' => [['keyword' => 'seo']]]]);
        (new AhrefsKeywordsOverviewNode)->execute(ahrefsRun(), ['country' => 'us', 'keywords' => 'seo,tools', 'select' => 'keyword,volume'], []);
        expect(ahrefsQuery(ahrefsLastRequest()))->toMatchArray(['keywords' => 'seo,tools', 'select' => 'keyword,volume', 'country' => 'us']);
    });

    it('reads a SERP and a rank tracker report', function () {
        ahrefsFake(['/serp-overview/serp-overview' => ['positions' => [['position' => 1]]], '/rank-tracker/overview' => ['overviews' => [['keyword' => 'seo']]]]);

        expect((new AhrefsSerpOverviewNode)->execute(ahrefsRun(), ['keyword' => 'seo', 'country' => 'us'], [])['positions'])->toBe([['position' => 1]])
            ->and((new AhrefsRankTrackerOverviewNode)->execute(ahrefsRun(), ['project_id' => '1', 'device' => 'desktop'], [])['count'])->toBe(1);
    });

    it('sends a batch analysis as a body, with columns as a list', function () {
        ahrefsFake(['POST /batch-analysis/batch-analysis' => ['targets' => [['url' => 'a.com', 'domain_rating' => 3]]]]);

        $output = (new AhrefsBatchAnalysisNode)->execute(ahrefsRun(), ['targets' => [['url' => 'a.com', 'mode' => 'domain', 'protocol' => 'both']]], []);
        $body = json_decode(ahrefsLastRequest()->body(), true);

        expect($output['count'])->toBe(1)
            ->and($body['targets'][0]['url'])->toBe('a.com')
            ->and($body['select'])->toContain('domain_rating');
    });

    it('asks Brand Radar with a body', function () {
        ahrefsFake(['POST /brand-radar/ai-responses' => ['ai_responses' => [['question' => 'Who?']]]]);

        (new AhrefsBrandRadarAiResponsesNode)->execute(ahrefsRun(), ['data_source' => ['chatgpt'], 'report_id' => 'r1', 'where' => '{"field":"x","is":["eq",1]}'], []);

        expect(json_decode(ahrefsLastRequest()->body(), true))->toMatchArray(['data_source' => ['chatgpt'], 'report_id' => 'r1', 'where' => ['field' => 'x', 'is' => ['eq', 1]]]);
    });

    it('changes a project in the query and the body', function () {
        ahrefsFake(['PUT /management/project-keywords' => ['keywords' => [['keyword' => 'seo']]], 'DELETE /management/projects' => ['projects' => []]]);

        (new AhrefsAddProjectKeywordsNode)->execute(ahrefsRun(), ['project_id' => '5', 'keywords' => [['keyword' => 'seo']], 'locations' => [['country' => 'us', 'language' => 'en']]], []);

        expect(ahrefsQuery(ahrefsLastRequest())['project_id'])->toBe('5')
            ->and(json_decode(ahrefsLastRequest()->body(), true)['keywords'])->toBe([['keyword' => 'seo']])
            ->and((new AhrefsAddProjectKeywordsNode)->effect([])->value)->toBe('write')
            ->and((new AhrefsDeleteProjectsNode)->effect([])->value)->toBe('destructive');
    });

    it('reads web analytics by a checked breakdown', function () {
        ahrefsFake(['/web-analytics/cities' => ['stats' => [['city' => 'Pune', 'visitors' => 3]]]]);

        expect((new AhrefsWebAnalyticsBreakdownNode)->execute(ahrefsRun(), ['project_id' => '1', 'dimension' => 'cities'], [])['stats'][0]['city'])->toBe('Pune')
            ->and(fn () => (new AhrefsWebAnalyticsBreakdownNode)->execute(ahrefsRun(), ['project_id' => '1', 'dimension' => '../admin'], []))->toThrow(InvalidArgumentException::class);
    });

    it('publishes a social post as something other people see', function () {
        ahrefsFake(['POST /social-media/post' => ['post' => ['post_id' => 'p1']]]);

        $node = new AhrefsSocialCreatePostNode;

        expect($node->execute(ahrefsRun(), ['channel_ids' => ['c1'], 'text_content' => 'Hi', 'timing' => 'now'], []))->toBe(['post_id' => 'p1'])
            ->and($node->effect([])->value)->toBe('external')
            ->and(json_decode(ahrefsLastRequest()->body(), true))->toMatchArray(['channel_ids' => ['c1'], 'text_content' => 'Hi']);
    });

    it('reads a content gap and Search Console keywords', function () {
        ahrefsFake(['POST /competitive-analysis/content-gap' => ['keywords' => [['keyword' => 'seo']]], '/gsc/keywords' => ['keywords' => [['keyword' => 'seo', 'clicks' => 3]]]]);

        expect((new AhrefsContentGapNode)->execute(ahrefsRun(), ['target' => ['url' => 'me.com', 'mode' => 'domain', 'protocol' => 'both'], 'competitors' => [['url' => 'them.com', 'mode' => 'domain', 'protocol' => 'both']], 'country' => 'us'], [])['count'])->toBe(1)
            ->and((new AhrefsGscKeywordsNode)->execute(ahrefsRun(), ['project_id' => '1', 'date_from' => '2026-01-01'], [])['keywords'][0]['clicks'])->toBe(3);
    });

    it('explains errors with a hint', function (int $status, string $hint) {
        ahrefsFake(['/site-explorer/domain-rating' => fn () => Http::response(['error' => 'No'], $status)]);

        expect(fn () => (new AhrefsDomainRatingNode)->execute(ahrefsRun(), ['target' => 'a.com'], []))->toThrow(RuntimeException::class, $hint);
    })->with([[401, 'Reconnect Ahrefs'], [403, 'does not allow'], [429, 'rate limiting'], [402, 'run out of API units']]);
});

describe('ahrefs triggers', function () {
    it('offers the triggers in the catalog', function () {
        $names = collect(app(NodeRegistry::class)->triggerCatalog())->where('category', 'ahrefs')->pluck('name');

        expect($names)->toContain('Ahrefs: New backlink', 'Ahrefs: Lost backlink', 'Ahrefs: New referring domain', 'Ahrefs: Lost referring domain', 'Ahrefs: New organic keyword', 'Ahrefs: Lost organic keyword', 'Ahrefs: Keyword rank changed', 'Ahrefs: Site audit crawl finished', 'Ahrefs: New site audit issue');
    });

    it('keeps only the domains that are gone', function () {
        ahrefsFake(['/site-explorer/refdomains' => ['refdomains' => [['domain' => 'a.com', 'last_seen' => '2026-01-01T00:00:00Z'], ['domain' => 'b.com', 'last_seen' => null]]]]);

        expect(array_column((new AhrefsListLostReferringDomainsNode)->execute(ahrefsRun(), ['target' => 'me.com'], [])['refdomains'], 'domain'))->toBe(['a.com']);
    });

    it('keeps only the keywords whose position moved', function () {
        ahrefsFake(['/rank-tracker/overview' => ['overviews' => [['keyword' => 'a', 'position' => 3, 'position_prev' => 5], ['keyword' => 'b', 'position' => 2, 'position_prev' => 2]]]]);

        expect(array_column((new AhrefsListRankChangesNode)->execute(ahrefsRun(), ['project_id' => '1', 'device' => 'desktop'], [])['keywords'], 'keyword'))->toBe(['a']);
    });

    it('fires for finished crawls and for issues on more URLs only', function () {
        expect((new AhrefsSiteAuditProjectsNode)->triggerItems(['healthscores' => [['project_id' => 1, 'status' => 'Completed'], ['project_id' => 2, 'status' => 'In_progress']]], []))->toBe([['project_id' => 1, 'status' => 'Completed']])
            ->and(array_column((new AhrefsSiteAuditIssuesNode)->triggerItems(['issues' => [['issue_id' => 'a', 'new' => 1, 'added' => 0], ['issue_id' => 'b', 'new' => 0, 'added' => 0]]], []), 'issue_id'))->toBe(['a']);
    });

    it('polls: a baseline first, then one run for each new item', function (string $type, array $nodeConfig, string $path, callable $answer, array $first, array $second, string $payloadKey, array $expected) {
        Queue::fake([FireTriggerEvent::class]);
        ahrefsFake([]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'ahrefs') ?? Connector::factory()->create(['key' => 'ahrefs']))
            ->create(['data' => ['api_key' => 'ah-secret'], 'created_by' => $owner->id]);
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

        ahrefsFake([$path => $answer($first)]);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        ahrefsFake([$path => $answer($second)]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck("payload.{$payloadKey}")->sort()->values()->all())->toBe($expected);
    })->with([
        'new backlink' => ['ahrefs_list_new_backlinks', ['target' => 'me.com'], '/site-explorer/all-backlinks', fn (array $from) => ['backlinks' => array_map(fn (string $s): array => ['url_from' => $s, 'url_to' => 'https://me.com'], $from)], ['https://a.com'], ['https://b.com', 'https://a.com'], 'url_from', ['https://b.com']],
        'lost backlink' => ['ahrefs_list_lost_backlinks', ['target' => 'me.com'], '/site-explorer/all-backlinks', fn (array $from) => ['backlinks' => array_map(fn (string $s): array => ['url_from' => $s, 'url_to' => 'https://me.com', 'last_seen' => '2026-01-01', 'is_lost' => true], $from)], ['https://a.com'], ['https://b.com', 'https://a.com'], 'url_from', ['https://b.com']],
        'new referring domain' => ['ahrefs_list_new_referring_domains', ['target' => 'me.com'], '/site-explorer/refdomains', fn (array $domains) => ['refdomains' => array_map(fn (string $s): array => ['domain' => $s], $domains)], ['a.com'], ['b.com', 'a.com'], 'domain', ['b.com']],
        'lost referring domain' => ['ahrefs_list_lost_referring_domains', ['target' => 'me.com'], '/site-explorer/refdomains', fn (array $domains) => ['refdomains' => array_map(fn (string $s): array => ['domain' => $s, 'last_seen' => '2026-01-01'], $domains)], ['a.com'], ['b.com', 'a.com'], 'domain', ['b.com']],
        'new keyword' => ['ahrefs_list_new_keywords', ['target' => 'me.com', 'country' => 'us'], '/site-explorer/organic-keywords', fn (array $words) => ['keywords' => array_map(fn (string $s): array => ['keyword' => $s], $words)], ['seo'], ['tools', 'seo'], 'keyword', ['tools']],
        'lost keyword' => ['ahrefs_list_lost_keywords', ['target' => 'me.com', 'country' => 'us'], '/site-explorer/organic-keywords', fn (array $words) => ['keywords' => array_map(fn (string $s): array => ['keyword_prev' => $s], $words)], ['seo'], ['tools', 'seo'], 'keyword', ['tools']],
        'rank change' => ['ahrefs_list_rank_changes', ['project_id' => '1', 'device' => 'desktop'], '/rank-tracker/overview', fn (array $words) => ['overviews' => array_map(fn (string $s): array => ['keyword' => $s, 'position' => 1, 'position_prev' => 2], $words)], ['seo'], ['tools', 'seo'], 'keyword', ['tools']],
        'crawl finished' => ['ahrefs_site_audit_projects', [], '/site-audit/projects', fn (array $dates) => ['healthscores' => array_map(fn (string $d): array => ['project_id' => 1, 'date' => $d, 'status' => 'Completed'], $dates)], ['2026-01-01'], ['2026-01-08'], 'date', ['2026-01-08']],
        'audit issue' => ['ahrefs_site_audit_issues', ['project_id' => '1'], '/site-audit/issues', fn (array $ids) => ['issues' => array_map(fn (string $id): array => ['issue_id' => $id, 'new' => 1, 'crawled' => 4], $ids)], ['a'], ['a', 'b'], 'issue_id', ['b']],
    ]);
});

describe('ahrefs options', function () {
    it('lists projects, keyword lists and reports, filtered by the search', function () {
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = fn (?string $search = null) => new NodeOptionsQuery($workspace, User::factory()->create(), [], 'ah-secret', $search);

        ahrefsFake(['/management/projects' => ['projects' => [['project_id' => 1, 'project_name' => 'Site', 'url' => 'a.com'], ['project_id' => 2, 'project_name' => 'Other', 'url' => 'b.com']]]]);
        expect(app(AhrefsOptions::class)->load('ahrefs.projects', $query('site'))->toArray()['options'])->toBe([['value' => '1', 'label' => 'Site', 'description' => 'a.com']]);

        ahrefsFake(['/management/keyword-lists' => ['keyword_lists' => [['keyword_list_id' => 4, 'name' => 'Main']]], '/management/brand-radar-reports' => ['reports' => [['report_id' => 'r', 'report_name' => 'Brand']]]]);
        expect(app(AhrefsOptions::class)->load('ahrefs.keyword_lists', $query())->toArray()['options'][0])->toBe(['value' => '4', 'label' => 'Main'])
            ->and(app(AhrefsOptions::class)->load('ahrefs.brand_radar_reports', $query())->toArray()['options'][0])->toBe(['value' => 'r', 'label' => 'Brand']);
    });
});

describe('ahrefs connector', function () {
    function ahrefsCredential(): ConnectorCredential
    {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

        return ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'ahrefs') ?? Connector::factory()->create(['key' => 'ahrefs', 'name' => 'Ahrefs']))
            ->create(['data' => ['api_key' => 'ah-secret'], 'created_by' => $owner->id]);
    }

    it('checks the key against its plan', function () {
        Http::fake(['https://api.ahrefs.com/v3/subscription-info/limits-and-usage*' => Http::response(['limits_and_usage' => ['subscription' => 'Enterprise']])]);

        expect(app(ConnectorCredentialTester::class)->test(ahrefsCredential()))->toMatchArray(['ok' => true, 'account' => 'Enterprise']);
    });

    it('fails the check on a refused key', function () {
        Http::fake(['https://api.ahrefs.com/*' => Http::response(['error' => 'Unauthorized'], 401)]);

        expect(app(ConnectorCredentialTester::class)->test(ahrefsCredential()))->toMatchArray(['ok' => false]);
    });
});

it('makes any API call on the ahrefs host, and refuses a path that leaves it', function () {
    ahrefsFake(['GET /subscription-info/limits-and-usage' => ['limits_and_usage' => ['units' => 5]]]);

    $node = app(NodeRegistry::class)->resolve('ahrefs_api_request');
    $run = ahrefsRun();
    $output = $node->execute($run, ['method' => 'GET', 'path' => '/subscription-info/limits-and-usage', 'query' => '{"a":"b"}'], []);

    expect($output)->toBe(['body' => ['limits_and_usage' => ['units' => 5]]])
        ->and(ahrefsQuery(ahrefsLastRequest()))->toMatchArray(['a' => 'b'])
        ->and(ahrefsLastRequest()->hasHeader('Authorization', 'Bearer ah-secret'))->toBeTrue()
        ->and($node->effect([])->value)->toBe('external');

    foreach (['https://evil.example/x', '/../x', '/a?b=1'] as $path) {
        expect(fn () => $node->execute($run, ['method' => 'GET', 'path' => $path], []))->toThrow(InvalidArgumentException::class);
    }
});
