<?php

use App\Enums\Agents\ActionEffect;
use App\Enums\Triggers\SubscriptionStatus;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Runs\Run;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Nodes\Integrations\Firecrawl\AbstractFirecrawlNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlApiRequestNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlGetCrawlStatusNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlInteractNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlParseFileNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlScrapeNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlSearchNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlStartAgentNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlStartCrawlNode;
use App\Nodes\Integrations\Firecrawl\FirecrawlStartExtractNode;
use App\Services\Triggers\Push\FirecrawlSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\FirecrawlOptions;
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
function fakeFirecrawl(array $routes, ?array $fallback = null): void
{
    webDataFake('*', '/v2', $routes, $fallback);
}

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function firecrawlConfig(array $config = []): array
{
    return ['access_token' => 'fc-key', ...$config];
}

function firecrawlTrigger(string $nodeType, array $nodeConfig = [], bool $sync = false): Trigger
{
    return webDataTrigger('firecrawl', $nodeType, $nodeConfig, $sync);
}

describe('firecrawl nodes', function () {
    it('registers the nodes under the firecrawl connector, each with a sound schema', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'firecrawl_'));

        expect(count($types))->toBeGreaterThanOrEqual(50);

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            $schema = $node->configSchema();

            expect($node)->toBeInstanceOf(AbstractFirecrawlNode::class)
                ->and($node->category())->toBe('firecrawl')
                ->and($node->name())->toStartWith('Firecrawl: ')
                ->and($node->effect([]))->toBeInstanceOf(ActionEffect::class)
                ->and(array_diff($schema['required'], array_keys($schema['properties'])))->toBe([]);
        }
    });

    it('runs every node with every setting filled, only ever calling Firecrawl', function () {
        fakeFirecrawl([], ['success' => true, 'id' => 'job1', 'data' => [], 'results' => []]);
        $registry = app(NodeRegistry::class);
        $run = Run::factory()->create();
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'firecrawl_'));

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            $config = webDataConfigFor($node->configSchema(), ['content' => 'aGVsbG8=', 'file_name' => 'a.pdf', 'path' => '/team/credit-usage', 'method' => 'POST']);

            expect($node->execute($run, $config, []))->toBeArray();
        }

        foreach (Http::recorded() as [$request]) {
            expect($request->url())->toStartWith('https://api.firecrawl.dev/v2/')->and($request->header('Authorization')[0])->toBe('Bearer the-key');
        }
    });

    it('scrapes a page with a bearer key, turning a prompt into a json format, and shapes the page', function () {
        fakeFirecrawl(['POST /scrape' => ['success' => true, 'data' => ['markdown' => '# Hi', 'metadata' => ['sourceURL' => 'https://a.io', 'title' => 'A', 'scrapeId' => 's1', 'statusCode' => 200]]]]);

        $output = (new FirecrawlScrapeNode)->execute(Run::factory()->create(), firecrawlConfig(['url' => 'https://a.io', 'formats' => 'markdown, links', 'json_prompt' => 'The price', 'only_main_content' => false, 'country' => 'DE']), []);

        expect($output)->toMatchArray(['url' => 'https://a.io', 'markdown' => '# Hi', 'scrape_id' => 's1', 'status_code' => 200])
            ->and(webDataBody('POST /scrape', '/v2'))->toBe(['url' => 'https://a.io', 'formats' => ['markdown', 'links', ['type' => 'json', 'prompt' => 'The price']], 'onlyMainContent' => false, 'location' => ['country' => 'DE']])
            ->and(webDataRequest('POST /scrape', '/v2')->header('Authorization')[0])->toBe('Bearer fc-key');
    });

    it('refuses what is not a web address before calling', function () {
        fakeFirecrawl([]);

        expect(fn () => (new FirecrawlScrapeNode)->execute(Run::factory()->create(), firecrawlConfig(['url' => 'file:///etc/passwd']), []))->toThrow(InvalidArgumentException::class, 'web address');

        Http::assertNothingSent();
    });

    it('searches and groups results by source', function () {
        fakeFirecrawl(['POST /search' => ['id' => 'q1', 'creditsUsed' => 2, 'data' => ['web' => [['url' => 'https://a.io']], 'news' => [['url' => 'https://b.io']]]]]);

        $output = (new FirecrawlSearchNode)->execute(Run::factory()->create(), firecrawlConfig(['query' => 'ai', 'sources' => ['web', 'news'], 'scrape_results' => true]), []);

        expect($output['web'][0]['url'])->toBe('https://a.io')->and($output['news'][0]['url'])->toBe('https://b.io')->and($output['credits_used'])->toBe(2)
            ->and(webDataBody('POST /search', '/v2')['scrapeOptions'])->toBe(['formats' => ['markdown']]);
    });

    it('starts a crawl at once, with its events going to the address given', function () {
        fakeFirecrawl(['POST /crawl' => ['success' => true, 'id' => 'c1', 'url' => 'https://api.firecrawl.dev/v2/crawl/c1']]);

        $output = (new FirecrawlStartCrawlNode)->execute(Run::factory()->create(), firecrawlConfig(['url' => 'https://a.io', 'limit' => 5, 'include_paths' => 'blog/.*', 'webhook_url' => 'https://hooks.io/x', 'webhook_events' => 'completed, page']), []);

        expect($output['id'])->toBe('c1')
            ->and(webDataBody('POST /crawl', '/v2'))->toMatchArray(['limit' => 5, 'includePaths' => ['blog/.*'], 'webhook' => ['url' => 'https://hooks.io/x', 'events' => ['completed', 'page']]]);
    });

    it('reads a crawl: how far it is and at most the pages asked for, and checks the id', function () {
        fakeFirecrawl(['GET /crawl/c1' => ['status' => 'scraping', 'total' => 9, 'completed' => 2, 'next' => 'https://x', 'data' => [['markdown' => 'a'], ['markdown' => 'b']]]]);

        $output = (new FirecrawlGetCrawlStatusNode)->execute(Run::factory()->create(), firecrawlConfig(['crawl_id' => 'c1', 'max_pages' => 1]), []);

        expect($output)->toMatchArray(['status' => 'scraping', 'finished' => false, 'total' => 9, 'more_pages' => true])->and($output['pages'])->toHaveCount(1)
            ->and(fn () => (new FirecrawlGetCrawlStatusNode)->execute(Run::factory()->create(), firecrawlConfig(['crawl_id' => '../team']), []))->toThrow(InvalidArgumentException::class);
    });

    it('asks an extract for a prompt or a schema', function () {
        fakeFirecrawl(['POST /extract' => ['id' => 'e1']]);

        expect((new FirecrawlStartExtractNode)->execute(Run::factory()->create(), firecrawlConfig(['urls' => ['https://a.io/*'], 'schema' => '{"type":"object"}']), [])['id'])->toBe('e1')
            ->and(webDataBody('POST /extract', '/v2')['schema'])->toBe(['type' => 'object'])
            ->and(fn () => (new FirecrawlStartExtractNode)->execute(Run::factory()->create(), firecrawlConfig(['urls' => ['https://a.io']]), []))->toThrow(InvalidArgumentException::class, 'what to extract');
    });

    it('starts an agent', function () {
        fakeFirecrawl(['POST /agent' => ['id' => 'a1']]);

        expect((new FirecrawlStartAgentNode)->execute(Run::factory()->create(), firecrawlConfig(['prompt' => 'Find the CEO', 'max_credits' => 50]), [])['id'])->toBe('a1')
            ->and(webDataBody('POST /agent', '/v2'))->toBe(['prompt' => 'Find the CEO', 'maxCredits' => 50]);
    });

    it('runs browser code on a scraped page with a bounded timeout', function () {
        fakeFirecrawl(['POST /scrape/s1/interact' => ['stdout' => 'ok', 'exitCode' => 0]]);

        $output = (new FirecrawlInteractNode)->execute(Run::factory()->create(), firecrawlConfig(['scrape_id' => 's1', 'code' => 'await page.click("a")', 'timeout' => 999]), []);

        expect($output)->toMatchArray(['stdout' => 'ok', 'exit_code' => 0])->and(webDataBody('POST /scrape/s1/interact', '/v2')['timeout'])->toBe(90);
    });

    it('uploads a file to parse, decoding base64 and refusing a bad one', function () {
        fakeFirecrawl(['POST /parse' => ['data' => ['markdown' => 'Parsed']]]);

        expect((new FirecrawlParseFileNode)->execute(Run::factory()->create(), firecrawlConfig(['file_name' => 'a.pdf', 'content' => base64_encode('%PDF'), 'is_base64' => true]), [])['markdown'])->toBe('Parsed')
            ->and(webDataRequest('POST /parse', '/v2')->body())->toContain('%PDF')
            ->and(fn () => (new FirecrawlParseFileNode)->execute(Run::factory()->create(), firecrawlConfig(['file_name' => 'a.pdf', 'content' => '!!!', 'is_base64' => true]), []))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new FirecrawlParseFileNode)->execute(Run::factory()->create(), firecrawlConfig(['file_name' => '../a', 'content' => 'x']), []))->toThrow(InvalidArgumentException::class);
    });

    it('keeps an API call on Firecrawl', function () {
        fakeFirecrawl(['GET /team/queue-status' => ['jobsInQueue' => 1]]);

        expect((new FirecrawlApiRequestNode)->execute(Run::factory()->create(), firecrawlConfig(['path' => '/team/queue-status']), [])['response']['jobsInQueue'])->toBe(1);

        foreach (['//evil.io/x', '/a/../b', 'https://evil.io', '/a?b=c'] as $path) {
            expect(fn () => (new FirecrawlApiRequestNode)->execute(Run::factory()->create(), firecrawlConfig(['path' => $path]), []))->toThrow(InvalidArgumentException::class);
        }

        expect((new FirecrawlApiRequestNode)->effect(['method' => 'DELETE']))->toBe(ActionEffect::Destructive)->and((new FirecrawlApiRequestNode)->effect(['method' => 'GET']))->toBe(ActionEffect::Read);
    });

    it('explains an error with a hint', function (int $status, string $hint) {
        fakeFirecrawl(['POST /scrape' => fn () => Http::response(['success' => false, 'error' => 'Nope'], $status)]);

        expect(fn () => (new FirecrawlScrapeNode)->execute(Run::factory()->create(), firecrawlConfig(['url' => 'https://a.io']), []))->toThrow(RuntimeException::class, $hint);
    })->with([[401, 'Reconnect Firecrawl'], [402, 'out of credits'], [403, 'not allowed'], [429, 'rate limiting']]);
});

describe('firecrawl options and connection', function () {
    it('lists monitors, filtered by the search', function () {
        fakeFirecrawl(['GET /monitor' => ['data' => [['id' => 'm1', 'name' => 'Pricing', 'status' => 'active'], ['id' => 'm2', 'name' => 'Blog']]]]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

        $page = app(FirecrawlOptions::class)->load('firecrawl.monitors', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'fc-key', 'pric'));

        expect($page->toArray()['options'])->toBe([['value' => 'm1', 'label' => 'Pricing', 'description' => 'active']]);
    });

    it('checks the key against the team credits', function () {
        fakeFirecrawl(['GET /team/credit-usage' => ['data' => ['remainingCredits' => 10]]]);
        expect(webDataCredentialCheck('firecrawl')['ok'])->toBeTrue();

        fakeFirecrawl(['GET /team/credit-usage' => fn () => Http::response(['error' => 'Unauthorized'], 401)]);
        expect(webDataCredentialCheck('firecrawl')['ok'])->toBeFalse();
    });
});

describe('firecrawl triggers', function () {
    it('fires a job finished trigger once, asking only about jobs still running', function () {
        $trigger = firecrawlTrigger('firecrawl_list_finished_crawls');
        $activity = fn (array $ids): array => ['data' => array_map(fn (string $id): array => ['id' => $id, 'endpoint' => 'crawl', 'target' => 'https://a.io', 'created_at' => '2026-01-31T09:00:00Z'], $ids)];

        fakeFirecrawl(['GET /team/activity' => $activity(['old'])]);
        webDataPoll($trigger);
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        fakeFirecrawl(['GET /team/activity' => $activity(['new', 'old']), 'GET /crawl/new' => ['status' => 'scraping']]);
        webDataPoll($trigger);
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/crawl/old'));

        fakeFirecrawl(['GET /team/activity' => $activity(['new', 'old']), 'GET /crawl/new' => ['status' => 'completed', 'total' => 3, 'completed' => 3, 'creditsUsed' => 3]]);
        webDataPoll($trigger);
        $event = $trigger->events()->where('status', 'queued')->sole();
        expect($event->payload)->toMatchArray(['id' => 'new', 'status' => 'completed', 'total' => 3]);

        fakeFirecrawl(['GET /team/activity' => $activity(['new', 'old'])]);
        webDataPoll($trigger);
        expect($trigger->events()->where('status', 'queued')->count())->toBe(1);
    });

    it('fires for agent runs that finished, and not those still going', function () {
        $trigger = firecrawlTrigger('firecrawl_list_agent_runs');
        $agents = fn (array $rows): array => ['agents' => $rows];

        fakeFirecrawl(['GET /agent' => $agents([['id' => 'a1', 'status' => 'completed']])]);
        webDataPoll($trigger);

        fakeFirecrawl(['GET /agent' => $agents([['id' => 'a3', 'status' => 'processing'], ['id' => 'a2', 'status' => 'failed'], ['id' => 'a1', 'status' => 'completed']])]);
        webDataPoll($trigger);

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->all())->toBe(['a2']);
    });

    it('polls monitor checks and page changes', function () {
        $trigger = firecrawlTrigger('firecrawl_list_monitor_changes', ['monitor_id' => 'm1']);
        $checks = ['data' => [['id' => 'k1', 'status' => 'completed'], ['id' => 'k0', 'status' => 'running']]];

        fakeFirecrawl(['GET /monitor/m1/checks' => $checks, 'GET /monitor/m1/checks/k1' => ['data' => ['pages' => []]]]);
        webDataPoll($trigger);

        fakeFirecrawl(['GET /monitor/m1/checks' => $checks, 'GET /monitor/m1/checks/k1' => ['data' => ['pages' => [['id' => 'p1', 'url' => 'https://a.io', 'status' => 'changed']]]]]);
        webDataPoll($trigger);

        expect($trigger->events()->where('status', 'queued')->sole()->payload)->toMatchArray(['id' => 'k1:p1', 'url' => 'https://a.io', 'status' => 'changed']);
    });

    it('claims a free monitor webhook with a token URL, and a delivery starts a poll', function () {
        fakeFirecrawl(['GET /monitor/m1' => ['data' => ['id' => 'm1', 'webhook' => null]], 'PATCH /monitor/m1' => ['data' => ['id' => 'm1']]]);
        $trigger = firecrawlTrigger('firecrawl_list_monitor_checks', ['monitor_id' => 'm1'], true);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('m1')->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(webDataBody('PATCH /monitor/m1', '/v2')['webhook'])->toBe(['url' => url('/api/hooks/apps/firecrawl').'?token='.$watch->secret, 'events' => ['monitor.check.completed']]);

        test()->postJson('/api/hooks/apps/firecrawl?token='.$watch->secret, ['type' => 'monitor.check.completed', 'id' => 'k1', 'webhookId' => 'd1'])->assertOk();
        test()->postJson('/api/hooks/apps/firecrawl?token='.$watch->secret, ['type' => 'monitor.check.completed', 'id' => 'k1', 'webhookId' => 'd1'])->assertOk();
        test()->postJson('/api/hooks/apps/firecrawl?token=nope', ['type' => 'monitor.check.completed'])->assertUnauthorized();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('leaves a monitor that posts elsewhere alone', function () {
        fakeFirecrawl(['GET /monitor/m1' => ['data' => ['id' => 'm1', 'webhook' => ['url' => 'https://theirs.io/hook']]]]);
        firecrawlTrigger('firecrawl_list_monitor_checks', ['monitor_id' => 'm1'], true);

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PATCH');
        expect(ProviderWatch::query()->count())->toBe(0);
    });

    it('takes the webhook back off when the watch goes, unless someone changed it', function () {
        fakeFirecrawl(['GET /monitor/m1' => ['data' => ['id' => 'm1', 'webhook' => null]], 'PATCH /monitor/m1' => ['data' => ['id' => 'm1']]]);
        firecrawlTrigger('firecrawl_list_monitor_checks', ['monitor_id' => 'm1'], true);
        $watch = ProviderWatch::query()->sole();

        fakeFirecrawl(['GET /monitor/m1' => ['data' => ['webhook' => ['url' => url('/api/hooks/apps/firecrawl').'?token=x']]], 'PATCH /monitor/m1' => ['data' => []]]);
        app(WatchManager::class)->remove(app(FirecrawlSource::class), $watch->fresh());

        expect(webDataBody('PATCH /monitor/m1', '/v2'))->toBe(['webhook' => null])->and(ProviderWatch::query()->count())->toBe(0);
    });
});
