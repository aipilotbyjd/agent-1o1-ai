<?php

use App\Enums\Agents\ActionEffect;
use App\Enums\Triggers\SubscriptionStatus;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Runs\Run;
use App\Models\Triggers\ProviderWatch;
use App\Models\User;
use App\Nodes\Integrations\Exa\AbstractExaNode;
use App\Nodes\Integrations\Exa\ExaAnswerNode;
use App\Nodes\Integrations\Exa\ExaCreateAgentRunNode;
use App\Nodes\Integrations\Exa\ExaCreateBatchNode;
use App\Nodes\Integrations\Exa\ExaCreateMonitorNode;
use App\Nodes\Integrations\Exa\ExaCreateWebsetMonitorNode;
use App\Nodes\Integrations\Exa\ExaCreateWebsetNode;
use App\Nodes\Integrations\Exa\ExaFindSimilarNode;
use App\Nodes\Integrations\Exa\ExaGetContentsNode;
use App\Nodes\Integrations\Exa\ExaGetTeamNode;
use App\Nodes\Integrations\Exa\ExaListWebsetsNode;
use App\Nodes\Integrations\Exa\ExaSearchNode;
use App\Services\Triggers\Push\ExaSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ExaOptions;
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
function fakeExa(array $routes, ?array $fallback = null): void
{
    webDataFake('*', '', $routes, $fallback);
}

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function exaConfig(array $config = []): array
{
    return ['access_token' => 'exa-key', ...$config];
}

describe('exa nodes', function () {
    it('registers the nodes under the exa connector, each with a sound schema', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'exa_'));

        expect(count($types))->toBeGreaterThanOrEqual(50);

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            $schema = $node->configSchema();

            expect($node)->toBeInstanceOf(AbstractExaNode::class)
                ->and($node->category())->toBe('exa')
                ->and($node->name())->toStartWith('Exa: ')
                ->and($node->effect([]))->toBeInstanceOf(ActionEffect::class)
                ->and(array_diff($schema['required'], array_keys($schema['properties'])))->toBe([]);
        }
    });

    it('runs every node with every setting filled, only ever calling Exa', function () {
        fakeExa([], ['id' => 'x', 'data' => [], 'results' => []]);
        $registry = app(NodeRegistry::class);
        $run = Run::factory()->create();
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'exa_'));

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            $config = webDataConfigFor($node->configSchema(), ['path' => '/monitors', 'method' => 'POST']);

            expect($node->execute($run, $config, []))->toBeArray();
        }

        foreach (Http::recorded() as [$request]) {
            expect($request->url())->toStartWith('https://api.exa.ai/')->and($request->header('x-api-key')[0])->toBe('the-key');
        }
    });

    it('searches with filters and contents, and shapes the results', function () {
        fakeExa(['POST /search' => ['requestId' => 'q1', 'costDollars' => ['total' => 0.01], 'results' => [['id' => 'd1', 'url' => 'https://a.io', 'title' => 'A', 'text' => 'Body', 'score' => 0.9]]]]);

        $output = (new ExaSearchNode)->execute(Run::factory()->create(), exaConfig(['query' => 'ai', 'type' => 'deep', 'num_results' => 3, 'include_domains' => 'a.io', 'text' => true, 'max_characters' => 500, 'summary_query' => 'Pricing', 'category' => 'news']), []);

        expect($output['results'][0])->toBe(['id' => 'd1', 'url' => 'https://a.io', 'title' => 'A', 'score' => 0.9, 'text' => 'Body'])
            ->and($output)->toMatchArray(['count' => 1, 'cost_usd' => 0.01, 'request_id' => 'q1'])
            ->and(webDataBody('POST /search', ''))->toBe(['query' => 'ai', 'type' => 'deep', 'numResults' => 3, 'category' => 'news', 'includeDomains' => ['a.io'], 'contents' => ['text' => ['maxCharacters' => 500], 'summary' => ['query' => 'Pricing']]])
            ->and(webDataRequest('POST /search', '')->header('x-api-key')[0])->toBe('exa-key');
    });

    it('reads contents by address or id, and asks for one of them', function () {
        fakeExa(['POST /contents' => ['results' => [['url' => 'https://a.io', 'text' => 'Body']], 'statuses' => [['id' => 'https://a.io', 'status' => 'success']]]]);

        expect((new ExaGetContentsNode)->execute(Run::factory()->create(), exaConfig(['urls' => ['https://a.io'], 'text' => true]), [])['results'][0]['text'])->toBe('Body')
            ->and(webDataBody('POST /contents', ''))->toBe(['urls' => ['https://a.io'], 'text' => true])
            ->and(fn () => (new ExaGetContentsNode)->execute(Run::factory()->create(), exaConfig(), []))->toThrow(InvalidArgumentException::class);
    });

    it('finds similar pages and answers with citations', function () {
        fakeExa(['POST /findSimilar' => ['results' => [['url' => 'https://b.io']]], 'POST /answer' => ['answer' => 'Yes', 'citations' => [['url' => 'https://a.io', 'title' => 'A']]]]);
        $run = Run::factory()->create();

        expect((new ExaFindSimilarNode)->execute($run, exaConfig(['url' => 'https://a.io', 'exclude_source_domain' => true]), [])['results'][0]['url'])->toBe('https://b.io')
            ->and(webDataBody('POST /findSimilar', ''))->toBe(['url' => 'https://a.io', 'excludeSourceDomain' => true])
            ->and((new ExaAnswerNode)->execute($run, exaConfig(['query' => 'Why?']), []))->toMatchArray(['answer' => 'Yes', 'citations' => [['url' => 'https://a.io', 'title' => 'A']]]);
    });

    it('starts research at once, with a budget', function () {
        fakeExa(['POST /agent/runs' => ['id' => 'r1', 'status' => 'queued', 'request' => ['query' => 'CEOs']]]);

        expect((new ExaCreateAgentRunNode)->execute(Run::factory()->create(), exaConfig(['query' => 'CEOs', 'effort' => 'high', 'max_cost_usd' => 2]), []))->toMatchArray(['id' => 'r1', 'status' => 'queued', 'finished' => false])
            ->and(webDataBody('POST /agent/runs', ''))->toBe(['query' => 'CEOs', 'effort' => 'high', 'budget' => ['maxCostDollars' => 2]]);
    });

    it('creates a search monitor with an interval and an address', function () {
        fakeExa(['POST /monitors' => ['id' => 'm1']]);

        (new ExaCreateMonitorNode)->execute(Run::factory()->create(), exaConfig(['query' => 'ai news', 'period' => '1d', 'webhook_url' => 'https://hooks.io/x']), []);

        expect(webDataBody('POST /monitors', ''))->toBe(['search' => ['query' => 'ai news'], 'trigger' => ['type' => 'interval', 'period' => '1d'], 'webhook' => ['url' => 'https://hooks.io/x']]);
    });

    it('sends the beta header on batches', function () {
        fakeExa(['POST /batches' => ['id' => 'b1']]);

        (new ExaCreateBatchNode)->execute(Run::factory()->create(), exaConfig(['requests' => [['customId' => 'a', 'method' => 'POST', 'url' => '/search', 'body' => ['query' => 'x']]]]), []);

        expect(webDataRequest('POST /batches', '')->header('Exa-Beta')[0])->toBe('batches-2026-06-06');
    });

    it('calls websets under their own root, and the team under the main one', function () {
        fakeExa(['POST /websets/v0/websets' => ['id' => 'ws1'], 'GET /websets/v0/websets' => ['data' => [['id' => 'ws1']], 'nextCursor' => null], 'POST /websets/v0/monitors' => ['id' => 'wm1'], 'GET /v0/teams/me' => ['id' => 't1', 'name' => 'Acme']]);
        $run = Run::factory()->create();

        expect((new ExaCreateWebsetNode)->execute($run, exaConfig(['query' => 'AI startups', 'count' => 5]), [])['webset']['id'])->toBe('ws1')
            ->and(webDataBody('POST /websets/v0/websets', '')['search'])->toBe(['query' => 'AI startups', 'count' => 5])
            ->and((new ExaListWebsetsNode)->execute($run, exaConfig(), [])['count'])->toBe(1)
            ->and((new ExaGetTeamNode)->execute($run, exaConfig(), [])['team']['name'])->toBe('Acme');

        (new ExaCreateWebsetMonitorNode)->execute($run, exaConfig(['webset_id' => 'ws1', 'cron' => '0 9 * * 1', 'query' => 'more', 'count' => 3]), []);

        expect(webDataBody('POST /websets/v0/monitors', ''))->toBe(['websetId' => 'ws1', 'cadence' => ['cron' => '0 9 * * 1'], 'behavior' => ['type' => 'search', 'config' => ['query' => 'more', 'count' => 3, 'behavior' => 'append']]]);
    });

    it('explains an error with a hint', function (int $status, string $hint) {
        fakeExa(['POST /answer' => fn () => Http::response(['error' => 'Nope'], $status)]);

        expect(fn () => (new ExaAnswerNode)->execute(Run::factory()->create(), exaConfig(['query' => 'x']), []))->toThrow(RuntimeException::class, $hint);
    })->with([[401, 'Reconnect Exa'], [402, 'out of credits'], [403, 'does not include'], [429, 'rate limiting']]);
});

describe('exa options and connection', function () {
    it('lists websets and monitors with the key in a header', function () {
        fakeExa(['GET /websets/v0/websets' => ['data' => [['id' => 'ws1', 'title' => 'Startups', 'status' => 'idle'], ['id' => 'ws2', 'title' => 'People']]], 'GET /monitors' => ['data' => [['id' => 'm1', 'name' => 'News']]]]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $source = app(ExaOptions::class);

        expect($source->load('exa.websets', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'exa-key', 'start'))->toArray()['options'])->toBe([['value' => 'ws1', 'label' => 'Startups', 'description' => 'idle']])
            ->and($source->load('exa.monitors', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'exa-key'))->toArray()['options'])->toBe([['value' => 'm1', 'label' => 'News']])
            ->and(webDataRequest('GET /monitors', '')->header('x-api-key')[0])->toBe('exa-key');
    });

    it('checks the key against the team', function () {
        fakeExa(['GET /v0/teams/me' => ['id' => 't1', 'name' => 'Acme']]);
        expect(webDataCredentialCheck('exa'))->toMatchArray(['ok' => true, 'account' => 'Acme']);

        fakeExa(['GET /v0/teams/me' => fn () => Http::response(['error' => 'bad'], 401)]);
        expect(webDataCredentialCheck('exa')['ok'])->toBeFalse();
    });
});

describe('exa triggers', function () {
    it('fires for research that finished, and not research still going', function () {
        $trigger = webDataTrigger('exa', 'exa_list_agent_runs', [], false);

        fakeExa(['GET /agent/runs' => ['data' => [['id' => 'r1', 'status' => 'completed']]]]);
        webDataPoll($trigger);

        fakeExa(['GET /agent/runs' => ['data' => [['id' => 'r3', 'status' => 'running'], ['id' => 'r2', 'status' => 'failed'], ['id' => 'r1', 'status' => 'completed']]]]);
        webDataPoll($trigger);

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->all())->toBe(['r2']);
    });

    it('fires for monitor runs that completed', function () {
        $trigger = webDataTrigger('exa', 'exa_list_monitor_runs', ['monitor_id' => 'm1'], false);

        fakeExa(['GET /monitors/m1/runs' => ['data' => [['id' => 'r1', 'status' => 'completed']]]]);
        webDataPoll($trigger);

        fakeExa(['GET /monitors/m1/runs' => ['data' => [['id' => 'r3', 'status' => 'running'], ['id' => 'r2', 'status' => 'completed'], ['id' => 'r1', 'status' => 'completed']]]]);
        webDataPoll($trigger);

        expect($trigger->events()->where('status', 'queued')->sole()->payload['id'])->toBe('r2');
    });

    it('polls webset items: a baseline first, then one run for each new item', function () {
        Http::preventStrayRequests();
        fakeExa(['POST /websets/v0/webhooks' => ['id' => 'wh_1'], 'GET /websets/v0/websets/ws1/items' => ['data' => [['id' => 'i1']]]]);
        $trigger = webDataTrigger('exa', 'exa_list_webset_items', ['webset_id' => 'ws1'], false);

        webDataPoll($trigger);

        fakeExa(['GET /websets/v0/websets/ws1/items' => ['data' => [['id' => 'i2'], ['id' => 'i1']]]]);
        webDataPoll($trigger);

        expect($trigger->events()->where('status', 'queued')->sole()->payload['id'])->toBe('i2');
    });

    it('registers a Websets webhook for new items with a token URL, and a delivery starts a poll once', function () {
        fakeExa(['POST /websets/v0/webhooks' => ['id' => 'wh_1', 'secret' => 's']]);
        $trigger = webDataTrigger('exa', 'exa_list_webset_items', ['webset_id' => 'ws1']);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('webset.item.created')
            ->and($watch->external_id)->toBe('wh_1')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(webDataBody('POST /websets/v0/webhooks', ''))->toBe(['url' => url('/api/hooks/apps/exa').'?token='.$watch->secret, 'events' => ['webset.item.created']]);

        $delivery = ['id' => 'ev1', 'object' => 'event', 'type' => 'webset.item.created', 'data' => ['id' => 'i1']];
        test()->postJson('/api/hooks/apps/exa?token='.$watch->secret, $delivery)->assertOk();
        test()->postJson('/api/hooks/apps/exa?token='.$watch->secret, $delivery)->assertOk();
        test()->postJson('/api/hooks/apps/exa?token=nope', $delivery)->assertUnauthorized();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('registers one webhook for the event chosen, and polls when none is', function () {
        fakeExa(['POST /websets/v0/webhooks' => ['id' => 'wh_2']]);
        webDataTrigger('exa', 'exa_list_events', ['event_type' => 'webset.idle']);
        webDataTrigger('exa', 'exa_list_events');

        expect(ProviderWatch::query()->sole()->resource_key)->toBe('webset.idle');
    });

    it('makes the webhook again when Exa ended it, and removes it with the watch', function () {
        fakeExa(['POST /websets/v0/webhooks' => ['id' => 'wh_1']]);
        webDataTrigger('exa', 'exa_list_webset_items', ['webset_id' => 'ws1']);
        $watch = ProviderWatch::query()->sole();
        $source = app(ExaSource::class);

        fakeExa(['GET /websets/v0/webhooks/wh_1' => ['id' => 'wh_1', 'status' => 'inactive'], 'POST /websets/v0/webhooks' => ['id' => 'wh_9']]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe('wh_9');

        fakeExa(['GET /websets/v0/webhooks/wh_9' => ['id' => 'wh_9', 'status' => 'active']]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');

        fakeExa(['DELETE /websets/v0/webhooks/wh_9' => null]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
