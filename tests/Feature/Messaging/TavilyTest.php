<?php

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Tavily\AbstractTavilyNode;
use App\Nodes\Integrations\Tavily\TavilyCrawlNode;
use App\Nodes\Integrations\Tavily\TavilyExtractNode;
use App\Nodes\Integrations\Tavily\TavilyGetLogsNode;
use App\Nodes\Integrations\Tavily\TavilyGetResearchNode;
use App\Nodes\Integrations\Tavily\TavilyGetUsageNode;
use App\Nodes\Integrations\Tavily\TavilyMapNode;
use App\Nodes\Integrations\Tavily\TavilySearchNode;
use App\Nodes\Integrations\Tavily\TavilyStartResearchNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/WebDataTestHelpers.php';

/**
 * @param  array<string, mixed>  $routes
 * @param  array<int|string, mixed>|null  $fallback
 */
function fakeTavily(array $routes, ?array $fallback = null): void
{
    webDataFake('*', '', $routes, $fallback);
}

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function tavilyConfig(array $config = []): array
{
    return ['access_token' => 'tvly-key', ...$config];
}

describe('tavily nodes', function () {
    it('registers the nodes under the tavily connector, each with a sound schema', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'tavily_'));

        expect(count($types))->toBeGreaterThanOrEqual(10);

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            $schema = $node->configSchema();

            expect($node)->toBeInstanceOf(AbstractTavilyNode::class)
                ->and($node->category())->toBe('tavily')
                ->and($node->name())->toStartWith('Tavily: ')
                ->and($node->effect([]))->toBeInstanceOf(ActionEffect::class)
                ->and(array_diff($schema['required'], array_keys($schema['properties'])))->toBe([]);
        }
    });

    it('runs every node with every setting filled, only ever calling Tavily', function () {
        fakeTavily([], ['results' => [], 'request_id' => 'x', 'status' => 'completed']);
        $registry = app(NodeRegistry::class);
        $run = Run::factory()->create();
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'tavily_'));

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            $config = webDataConfigFor($node->configSchema(), ['path' => '/usage', 'method' => 'POST']);

            expect($node->execute($run, $config, []))->toBeArray();
        }

        foreach (Http::recorded() as [$request]) {
            expect($request->url())->toStartWith('https://api.tavily.com/')->and($request->header('Authorization')[0])->toBe('Bearer the-key');
        }
    });

    it('searches with a bearer key and leaves out what was not asked for', function () {
        fakeTavily(['POST /search' => ['query' => 'ai', 'answer' => 'Yes', 'images' => [], 'results' => [['title' => 'A', 'url' => 'https://a.io', 'content' => 'Text', 'score' => 0.9]], 'response_time' => 1.1, 'usage' => ['credits' => 1], 'request_id' => 'q1']]);

        $output = (new TavilySearchNode)->execute(Run::factory()->create(), tavilyConfig(['query' => 'ai', 'topic' => 'news', 'include_answer' => 'basic', 'include_raw_content' => 'none', 'time_range' => 'week', 'include_domains' => 'a.io, b.io']), []);

        expect($output)->toMatchArray(['answer' => 'Yes', 'credits' => 1, 'request_id' => 'q1'])->and($output['results'][0]['url'])->toBe('https://a.io')
            ->and(webDataBody('POST /search', ''))->toBe(['query' => 'ai', 'topic' => 'news', 'include_answer' => 'basic', 'time_range' => 'week', 'include_domains' => ['a.io', 'b.io'], 'include_usage' => true])
            ->and(webDataRequest('POST /search', '')->header('Authorization')[0])->toBe('Bearer tvly-key');
    });

    it('extracts up to 20 pages, and reports those that failed', function () {
        fakeTavily(['POST /extract' => ['results' => [['url' => 'https://a.io', 'raw_content' => 'Body']], 'failed_results' => [['url' => 'https://b.io', 'error' => 'blocked']]]]);
        $run = Run::factory()->create();

        $output = (new TavilyExtractNode)->execute($run, tavilyConfig(['urls' => ['https://a.io', 'https://b.io'], 'query' => 'pricing']), []);

        expect($output['results'][0]['raw_content'])->toBe('Body')->and($output['failed'][0]['url'])->toBe('https://b.io')
            ->and(webDataBody('POST /extract', ''))->toMatchArray(['urls' => ['https://a.io', 'https://b.io'], 'query' => 'pricing', 'timeout' => 30])
            ->and(fn () => (new TavilyExtractNode)->execute($run, tavilyConfig(['urls' => array_map(fn (int $n): string => "https://a.io/{$n}", range(1, 21))]), []))->toThrow(InvalidArgumentException::class, '20 pages')
            ->and(fn () => (new TavilyExtractNode)->execute($run, tavilyConfig(['urls' => ['ftp://a.io']]), []))->toThrow(InvalidArgumentException::class);
    });

    it('crawls and maps with a timeout kept below a worker\'s limit', function () {
        fakeTavily(['POST /crawl' => ['base_url' => 'a.io', 'results' => [['url' => 'https://a.io', 'raw_content' => 'Body']]], 'POST /map' => ['base_url' => 'a.io', 'results' => ['https://a.io', 'https://a.io/b']]]);
        $run = Run::factory()->create();

        expect((new TavilyCrawlNode)->execute($run, tavilyConfig(['url' => 'https://a.io', 'limit' => 10, 'timeout' => 999, 'select_paths' => '/docs/.*']), [])['count'])->toBe(1)
            ->and(webDataBody('POST /crawl', ''))->toMatchArray(['url' => 'https://a.io', 'limit' => 10, 'timeout' => 90, 'select_paths' => ['/docs/.*']])
            ->and((new TavilyMapNode)->execute($run, tavilyConfig(['url' => 'https://a.io']), []))->toMatchArray(['urls' => ['https://a.io', 'https://a.io/b'], 'count' => 2]);
    });

    it('starts research at once and reads it by id, while running and when done', function () {
        fakeTavily(['POST /research' => ['request_id' => 'r1', 'status' => 'pending', 'model' => 'pro', 'created_at' => '2026-01-31T09:00:00Z', 'input' => 'x', 'response_time' => 1], 'GET /research/r1' => fn () => Http::response(['request_id' => 'r1', 'status' => 'in_progress', 'response_time' => 1], 202)]);
        $run = Run::factory()->create();

        expect((new TavilyStartResearchNode)->execute($run, tavilyConfig(['input' => 'A report', 'model' => 'pro', 'output_length' => 'long']), []))->toMatchArray(['id' => 'r1', 'status' => 'pending'])
            ->and(webDataBody('POST /research', ''))->toBe(['input' => 'A report', 'model' => 'pro', 'output_length' => 'long'])
            ->and((new TavilyGetResearchNode)->execute($run, tavilyConfig(['request_id' => 'r1']), []))->toMatchArray(['status' => 'in_progress', 'finished' => false]);

        fakeTavily(['GET /research/r1' => ['request_id' => 'r1', 'status' => 'completed', 'content' => 'Report', 'sources' => [['url' => 'https://a.io']]]]);

        expect((new TavilyGetResearchNode)->execute($run, tavilyConfig(['request_id' => 'r1']), []))->toMatchArray(['status' => 'completed', 'finished' => true, 'content' => 'Report'])
            ->and(fn () => (new TavilyGetResearchNode)->execute($run, tavilyConfig(['request_id' => '../usage']), []))->toThrow(InvalidArgumentException::class);
    });

    it('reads usage and logs', function () {
        fakeTavily(['GET /usage' => ['key' => ['usage' => 5], 'account' => ['current_plan' => 'Bootstrap']], 'POST /logs' => ['logs' => [['endpoint' => 'search']], 'count' => 1]]);
        $run = Run::factory()->create();

        expect((new TavilyGetUsageNode)->execute($run, tavilyConfig(), [])['account']['current_plan'])->toBe('Bootstrap')
            ->and((new TavilyGetLogsNode)->execute($run, tavilyConfig(['endpoints' => 'search, map', 'limit' => 5]), []))->toMatchArray(['count' => 1])
            ->and(webDataBody('POST /logs', ''))->toBe(['limit' => 5, 'endpoints' => ['search', 'map']]);
    });

    it('explains an error with a hint, whatever shape its detail has', function (int $status, array $body, string $hint) {
        fakeTavily(['POST /search' => fn () => Http::response($body, $status)]);

        expect(fn () => (new TavilySearchNode)->execute(Run::factory()->create(), tavilyConfig(['query' => 'x']), []))->toThrow(RuntimeException::class, $hint);
    })->with([
        [401, ['detail' => ['error' => 'Unauthorized']], 'Reconnect Tavily'],
        [432, ['detail' => ['error' => 'Over plan']], 'plan or pay-as-you-go'],
        [433, ['detail' => 'Over paygo'], 'plan or pay-as-you-go'],
        [403, ['detail' => ['error' => 'No']], 'not allowed'],
        [429, ['detail' => ['error' => 'Slow']], 'rate limiting'],
    ]);

    it('checks the key against usage and shows the plan', function () {
        fakeTavily(['GET /usage' => ['key' => ['usage' => 1], 'account' => ['current_plan' => 'Bootstrap']]]);
        expect(webDataCredentialCheck('tavily'))->toMatchArray(['ok' => true, 'account' => 'Bootstrap']);

        fakeTavily(['GET /usage' => fn () => Http::response(['detail' => ['error' => 'bad']], 401)]);
        expect(webDataCredentialCheck('tavily')['ok'])->toBeFalse();
    });
});
