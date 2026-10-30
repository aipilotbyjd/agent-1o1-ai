<?php

use App\Enums\Agents\ActionEffect;
use App\Exceptions\ConnectorException;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\MetaAds\AbstractMetaAdsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsAddEmailToAudienceNode;
use App\Nodes\Integrations\MetaAds\MetaAdsAddPeopleToAudienceNode;
use App\Nodes\Integrations\MetaAds\MetaAdsArchiveCampaignNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCopyCampaignNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateAdNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateAdSetNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateAdsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateAudienceNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateCampaignNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateCatalogNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateCreativeNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateLeadFormNode;
use App\Nodes\Integrations\MetaAds\MetaAdsCreateLookalikeNode;
use App\Nodes\Integrations\MetaAds\MetaAdsDeleteCampaignNode;
use App\Nodes\Integrations\MetaAds\MetaAdsDeleteCampaignsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetAccountNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetInsightsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetInsightsReportNode;
use App\Nodes\Integrations\MetaAds\MetaAdsGetLeadNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListAccountsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListAdSetsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListChangedCampaignsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListLeadFormsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListLeadsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListNewAdSetsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListNewCampaignsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListNewLeadsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListPagePostsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListPostCommentsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsListProductsNode;
use App\Nodes\Integrations\MetaAds\MetaAdsPreviewAdNode;
use App\Nodes\Integrations\MetaAds\MetaAdsRemoveEmailFromAudienceNode;
use App\Nodes\Integrations\MetaAds\MetaAdsSearchTargetingNode;
use App\Nodes\Integrations\MetaAds\MetaAdsSendConversionEventNode;
use App\Nodes\Integrations\MetaAds\MetaAdsSendFunnelEventNode;
use App\Nodes\Integrations\MetaAds\MetaAdsSendPurchaseEventNode;
use App\Nodes\Integrations\MetaAds\MetaAdsSetAdStatusNode;
use App\Nodes\Integrations\MetaAds\MetaAdsSetCampaignStatusNode;
use App\Nodes\Integrations\MetaAds\MetaAdsStartInsightsReportNode;
use App\Nodes\Integrations\MetaAds\MetaAdsUpdateAdSetNode;
use App\Nodes\Integrations\MetaAds\MetaAdsUpdateCampaignNode;
use App\Nodes\Integrations\MetaAds\MetaAdsUploadImageNode;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\MetaAdsOptions;
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

function metaAdsRun(): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'meta_ads') ?? Connector::factory()->create(['key' => 'meta_ads']))->id,
        'data' => ['access_token' => 'meta-token'],
        'is_default' => true,
    ]);

    return $run;
}

function metaAdsKey(Request $request): string
{
    return $request->method().' '.preg_replace('~^/v26\.0~', '', rawurldecode((string) parse_url($request->url(), PHP_URL_PATH)));
}

/**
 * Answer the Graph API. A key is "METHOD /path" under the version.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeMetaAds(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['graph.facebook.com/*' => function (Request $request) use ($routes) {
        $key = metaAdsKey($request);
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['error' => ['message' => "Nothing fakes {$key}", 'code' => 100]], 400);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function metaAdsRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => metaAdsKey($pair[0]) === $route));

    expect($found)->not->toBeEmpty();

    return $found[0][0];
}

/**
 * The params of a request, with the JSON ones decoded.
 *
 * @return array<string, mixed>
 */
function metaAdsParams(string $route): array
{
    $request = metaAdsRequest($route);
    $text = $request->method() === 'POST' ? $request->body() : (string) parse_url($request->url(), PHP_URL_QUERY);

    parse_str($text, $params);

    return array_map(fn (mixed $value): mixed => is_string($value) && in_array($value[0] ?? '', ['[', '{'], true) ? json_decode($value, true) : $value, $params);
}

describe('meta ads registry and connector', function () {
    it('registers every node under the meta_ads connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'meta_ads_'));

        expect(count($types))->toBeGreaterThanOrEqual(90);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractMetaAdsNode::class)->and($registry->resolve($type)->category())->toBe('meta_ads');
        }
    });

    it('seeds the connector with its login', function () {
        $this->seed([ConnectorSeeder::class, AppNodeCategorySeeder::class]);

        $connector = Connector::query()->where('key', 'meta_ads')->firstOrFail();

        expect($connector->oauth['token_url'])->toBe('https://graph.facebook.com/v26.0/oauth/access_token')
            ->and($connector->oauth['scopes'])->toContain('ads_management', 'ads_read', 'leads_retrieval', 'pages_manage_ads');
    });

    it('checks the connection by asking who the token belongs to', function () {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector(Connector::factory()->create(['key' => 'meta_ads', 'name' => 'Meta Ads']))->create(['data' => ['access_token' => 'meta-token'], 'created_by' => $owner->id]);

        fakeMetaAds(['GET /me' => ['id' => '1', 'name' => 'Ada']]);
        $good = app(ConnectorCredentialTester::class)->test($credential);

        expect($good['ok'])->toBeTrue()->and($good['account'])->toBe('Ada');

        fakeMetaAds(['GET /me' => fn () => Http::response(['error' => ['message' => 'Bad', 'code' => 190]], 401)]);

        expect(app(ConnectorCredentialTester::class)->test($credential)['ok'])->toBeFalse();
    });
});

describe('meta ads requests', function () {
    it('sends the token in a header, and pages by cursor', function () {
        fakeMetaAds(['GET /me/adaccounts' => ['data' => [['id' => 'act_1', 'name' => 'Main']], 'paging' => ['cursors' => ['after' => 'abc'], 'next' => 'https://x']]]);

        $output = (new MetaAdsListAccountsNode)->execute(metaAdsRun(), ['limit' => 5], []);
        $request = metaAdsRequest('GET /me/adaccounts');

        expect($output)->toMatchArray(['count' => 1, 'next_cursor' => 'abc'])
            ->and($request->header('Authorization')[0])->toBe('Bearer meta-token')
            ->and($request->url())->toStartWith('https://graph.facebook.com/v26.0/me/adaccounts')->not->toContain('meta-token')
            ->and(metaAdsParams('GET /me/adaccounts')['limit'])->toBe('5');
    });

    it('takes an account with or without act_, and refuses what cannot sit in a URL', function () {
        fakeMetaAds(['GET /act_123' => ['id' => 'act_123']]);

        expect((new MetaAdsGetAccountNode)->execute(metaAdsRun(), ['account_id' => '123'], [])['account']['id'])->toBe('act_123')
            ->and((new MetaAdsGetAccountNode)->execute(metaAdsRun(), ['account_id' => 'act_123'], [])['account']['id'])->toBe('act_123')
            ->and(fn () => (new MetaAdsGetAccountNode)->execute(metaAdsRun(), ['account_id' => '1/../2'], []))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new MetaAdsDeleteCampaignNode)->execute(metaAdsRun(), ['campaign_id' => '1?x=1'], []))->toThrow(InvalidArgumentException::class);
    });

    it('explains errors with a hint', function (int $status, int $code, string $expected) {
        fakeMetaAds(['GET /me/adaccounts' => fn () => Http::response(['error' => ['message' => 'Refused', 'code' => $code]], $status)]);

        expect(fn () => (new MetaAdsListAccountsNode)->execute(metaAdsRun(), [], []))->toThrow(RuntimeException::class, $expected);
    })->with([[401, 190, 'Reconnect Meta Ads'], [403, 200, 'permission'], [400, 80004, 'rate limiting'], [400, 4, 'rate limiting']]);

    it('prefers the sentence Meta wrote for people', function () {
        fakeMetaAds(['GET /me/adaccounts' => fn () => Http::response(['error' => ['message' => 'Invalid parameter', 'error_user_msg' => 'The budget is too low.', 'code' => 100]], 400)]);

        expect(fn () => (new MetaAdsListAccountsNode)->execute(metaAdsRun(), [], []))->toThrow(RuntimeException::class, 'The budget is too low.');
    });
});

describe('meta ads money safety', function () {
    it('creates a campaign paused unless told otherwise, and declares a live one as external', function () {
        fakeMetaAds(['POST /act_1/campaigns' => ['id' => '10']]);
        $node = new MetaAdsCreateCampaignNode;

        $output = $node->execute(metaAdsRun(), ['account_id' => '1', 'name' => 'Spring', 'objective' => 'OUTCOME_LEADS'], []);
        $params = metaAdsParams('POST /act_1/campaigns');

        expect($output)->toBe(['id' => '10', 'status' => 'PAUSED'])
            ->and($params)->toMatchArray(['name' => 'Spring', 'objective' => 'OUTCOME_LEADS', 'status' => 'PAUSED', 'special_ad_categories' => [], 'is_adset_budget_sharing_enabled' => 'false'])
            ->and($node->effect([]))->toBe(ActionEffect::Write)
            ->and($node->effect(['status' => 'ACTIVE']))->toBe(ActionEffect::External)
            ->and($node->effect(['status' => '{{ a.b }}']))->toBe(ActionEffect::External);
    });

    it('keeps a templated status from reading as paused, and creates ad sets and ads paused', function () {
        fakeMetaAds(['POST /act_1/adsets' => ['id' => '20'], 'POST /act_1/ads' => ['id' => '30']]);

        $set = (new MetaAdsCreateAdSetNode)->execute(metaAdsRun(), ['account_id' => '1', 'campaign_id' => '10', 'name' => 'S', 'billing_event' => 'IMPRESSIONS', 'optimization_goal' => 'REACH', 'targeting' => ['geo_locations' => ['countries' => ['US']]], 'daily_budget' => 1000], []);
        $ad = (new MetaAdsCreateAdNode)->execute(metaAdsRun(), ['account_id' => '1', 'ad_set_id' => '20', 'name' => 'A', 'creative_id' => '40'], []);

        expect($set['status'])->toBe('PAUSED')->and($ad['status'])->toBe('PAUSED')
            ->and(metaAdsParams('POST /act_1/adsets'))->toMatchArray(['status' => 'PAUSED', 'targeting' => ['geo_locations' => ['countries' => ['US']]], 'daily_budget' => '1000'])
            ->and(metaAdsParams('POST /act_1/ads'))->toMatchArray(['status' => 'PAUSED', 'adset_id' => '20', 'creative' => ['creative_id' => '40']])
            ->and((new MetaAdsCreateAdSetNode)->effect(['status' => 'ACTIVE']))->toBe(ActionEffect::External)
            ->and(fn () => (new MetaAdsCreateAdNode)->execute(metaAdsRun(), ['account_id' => '1', 'ad_set_id' => '20', 'name' => 'A'], []))->toThrow(InvalidArgumentException::class, 'creative');
    });

    it('creates ads in bulk, each paused unless it says so, and reports each', function () {
        fakeMetaAds(['POST /' => [['code' => 200, 'body' => '{"id":"31"}'], ['code' => 400, 'body' => '{"error":{"message":"No"}}']]]);
        $node = new MetaAdsCreateAdsNode;
        $ads = [['name' => 'A', 'adset_id' => '20', 'creative_id' => '40'], ['name' => 'B', 'adset_id' => '20', 'creative_id' => '41', 'status' => 'ACTIVE']];

        $output = $node->execute(metaAdsRun(), ['account_id' => '1', 'ads' => $ads], []);
        $batch = metaAdsParams('POST /')['batch'];

        parse_str($batch[0]['body'], $first);
        parse_str($batch[1]['body'], $second);

        expect($output['results'][0])->toMatchArray(['ok' => true, 'body' => ['id' => '31']])
            ->and($output['results'][1]['ok'])->toBeFalse()
            ->and($batch[0])->toMatchArray(['method' => 'POST', 'relative_url' => 'act_1/ads'])
            ->and($first['status'])->toBe('PAUSED')->and($second['status'])->toBe('ACTIVE')
            ->and($node->effect(['ads' => $ads]))->toBe(ActionEffect::External)
            ->and($node->effect(['ads' => [$ads[0]]]))->toBe(ActionEffect::Write)
            ->and(fn () => $node->execute(metaAdsRun(), ['account_id' => '1', 'ads' => array_fill(0, 51, $ads[0])], []))->toThrow(InvalidArgumentException::class, 'at most 50');
    });

    it('sets a status, and declares it by what it does', function () {
        fakeMetaAds(['POST /10' => ['success' => true], 'POST /30' => ['success' => true]]);
        $node = new MetaAdsSetCampaignStatusNode;

        expect($node->execute(metaAdsRun(), ['campaign_id' => '10', 'status' => 'active'], [])['status'])->toBe('ACTIVE')
            ->and(metaAdsParams('POST /10')['status'])->toBe('ACTIVE')
            ->and($node->effect(['status' => 'ACTIVE']))->toBe(ActionEffect::External)
            ->and($node->effect(['status' => 'PAUSED']))->toBe(ActionEffect::Write)
            ->and((new MetaAdsSetAdStatusNode)->execute(metaAdsRun(), ['ad_id' => '30', 'status' => 'PAUSED'], [])['status'])->toBe('PAUSED');
    });

    it('declares budget changes as spending, and wants something to change', function () {
        fakeMetaAds(['POST /20' => ['success' => true]]);

        expect((new MetaAdsUpdateCampaignNode)->effect(['daily_budget' => 100]))->toBe(ActionEffect::External)
            ->and((new MetaAdsUpdateCampaignNode)->effect(['name' => 'X']))->toBe(ActionEffect::Write)
            ->and((new MetaAdsUpdateCampaignNode)->effect(['status' => 'ACTIVE']))->toBe(ActionEffect::External)
            ->and((new MetaAdsUpdateAdSetNode)->effect(['targeting' => ['a' => 1]]))->toBe(ActionEffect::External)
            ->and((new MetaAdsUpdateAdSetNode)->execute(metaAdsRun(), ['ad_set_id' => '20', 'name' => 'New'], [])['updated'])->toBeTrue()
            ->and(fn () => (new MetaAdsUpdateCampaignNode)->execute(metaAdsRun(), ['campaign_id' => '10'], []))->toThrow(InvalidArgumentException::class, 'at least one');
    });

    it('archives, deletes one and many, and declares deletes', function () {
        fakeMetaAds(['POST /10' => ['success' => true], 'DELETE /10' => ['success' => true], 'DELETE /act_1/campaigns' => ['deleted_object_ids' => ['10'], 'objects_left_to_delete_count' => 0]]);

        expect((new MetaAdsArchiveCampaignNode)->execute(metaAdsRun(), ['campaign_id' => '10'], [])['status'])->toBe('ARCHIVED')
            ->and((new MetaAdsDeleteCampaignNode)->execute(metaAdsRun(), ['campaign_id' => '10'], [])['deleted'])->toBeTrue()
            ->and((new MetaAdsDeleteCampaignNode)->effect([]))->toBe(ActionEffect::Destructive)
            ->and((new MetaAdsDeleteCampaignsNode)->execute(metaAdsRun(), ['account_id' => '1', 'delete_strategy' => 'DELETE_OLDEST', 'object_count' => 1], [])['deleted_object_ids'])->toBe(['10'])
            ->and(metaAdsParams('DELETE /act_1/campaigns')['delete_strategy'])->toBe('DELETE_OLDEST')
            ->and((new MetaAdsDeleteCampaignsNode)->effect([]))->toBe(ActionEffect::Destructive);
    });

    it('copies paused by default', function () {
        fakeMetaAds(['POST /10/copies' => ['copied_campaign_id' => '11']]);
        $node = new MetaAdsCopyCampaignNode;

        expect($node->execute(metaAdsRun(), ['campaign_id' => '10'], [])['copied_campaign_id'])->toBe('11')
            ->and(metaAdsParams('POST /10/copies')['status_option'])->toBe('PAUSED')
            ->and($node->effect([]))->toBe(ActionEffect::Write)
            ->and($node->effect(['status_option' => 'ACTIVE']))->toBe(ActionEffect::External);
    });
});

describe('meta ads reads', function () {
    it('lists ad sets of a campaign when one is given', function () {
        fakeMetaAds(['GET /10/adsets' => ['data' => [['id' => '20']]], 'GET /act_1/adsets' => ['data' => []]]);

        expect((new MetaAdsListAdSetsNode)->execute(metaAdsRun(), ['account_id' => '1', 'campaign_id' => '10', 'effective_status' => ['active']], [])['ad_sets'][0]['id'])->toBe('20')
            ->and(metaAdsParams('GET /10/adsets')['effective_status'])->toBe(['ACTIVE']);
    });

    it('reads insights for the narrowest object given, with a date range over a preset', function () {
        fakeMetaAds(['GET /10/insights' => ['data' => [['spend' => '12.50']]], 'GET /act_1/insights' => ['data' => []]]);

        $output = (new MetaAdsGetInsightsNode)->execute(metaAdsRun(), ['account_id' => '1', 'campaign_id' => '10', 'level' => 'ad', 'since' => '2026-01-01', 'until' => '2026-01-31', 'date_preset' => 'last_7d', 'breakdowns' => ['age', 'gender'], 'sort' => 'reach_descending', 'fields' => ['spend']], []);
        $params = metaAdsParams('GET /10/insights');

        expect($output['rows'][0]['spend'])->toBe('12.50')
            ->and($params)->toMatchArray(['fields' => 'spend', 'level' => 'ad', 'time_range' => ['since' => '2026-01-01', 'until' => '2026-01-31'], 'breakdowns' => 'age,gender', 'sort' => ['reach_descending']])
            ->and($params)->not->toHaveKey('date_preset');
    });

    it('starts a background report and reads it once finished', function () {
        fakeMetaAds(['POST /act_1/insights' => ['report_run_id' => 77], 'GET /77' => ['id' => '77', 'async_status' => 'Job Completed', 'async_percent_completion' => 100], 'GET /77/insights' => ['data' => [['spend' => '1']]], 'GET /88' => ['id' => '88', 'async_status' => 'Job Running', 'async_percent_completion' => 40]]);

        expect((new MetaAdsStartInsightsReportNode)->execute(metaAdsRun(), ['account_id' => '1'], [])['report_run_id'])->toBe('77')
            ->and((new MetaAdsGetInsightsReportNode)->execute(metaAdsRun(), ['report_run_id' => '77'], []))->toMatchArray(['finished' => true, 'count' => 1])
            ->and((new MetaAdsGetInsightsReportNode)->execute(metaAdsRun(), ['report_run_id' => '88'], []))->toMatchArray(['finished' => false, 'percent' => 40, 'rows' => []]);
    });

    it('creates a creative, previews an ad, uploads an image and searches targeting', function () {
        fakeMetaAds(['POST /act_1/adcreatives' => ['id' => '40'], 'GET /30/previews' => ['data' => [['body' => '<iframe>']]], 'POST /act_1/adimages' => ['images' => ['bytes' => ['hash' => 'abc', 'url' => 'https://i']]], 'GET /search' => ['data' => [['id' => '6003', 'name' => 'Golf']]]]);

        expect((new MetaAdsCreateCreativeNode)->execute(metaAdsRun(), ['account_id' => '1', 'object_story_id' => '1_2'], [])['id'])->toBe('40')
            ->and(fn () => (new MetaAdsCreateCreativeNode)->execute(metaAdsRun(), ['account_id' => '1'], []))->toThrow(InvalidArgumentException::class)
            ->and((new MetaAdsPreviewAdNode)->execute(metaAdsRun(), ['ad_id' => '30', 'ad_format' => 'MOBILE_FEED_STANDARD'], [])['previews'][0]['body'])->toBe('<iframe>')
            ->and((new MetaAdsUploadImageNode)->execute(metaAdsRun(), ['account_id' => '1', 'bytes' => 'AAA='], [])['hash'])->toBe('abc')
            ->and((new MetaAdsSearchTargetingNode)->execute(metaAdsRun(), ['type' => 'adinterest', 'q' => 'golf'], [])['options'][0]['name'])->toBe('Golf');
    });

    it('reads Page content with the Page token', function () {
        fakeMetaAds(['GET /5' => ['access_token' => 'page-token'], 'GET /5/posts' => ['data' => [['id' => '5_9']]], 'GET /5_9/comments' => ['data' => [['id' => 'c']]]]);

        expect((new MetaAdsListPagePostsNode)->execute(metaAdsRun(), ['page_id' => '5'], [])['posts'][0]['id'])->toBe('5_9')
            ->and(metaAdsRequest('GET /5/posts')->header('Authorization')[0])->toBe('Bearer page-token')
            ->and((new MetaAdsListPostCommentsNode)->execute(metaAdsRun(), ['page_id' => '5', 'post_id' => '5_9'], [])['comments'][0]['id'])->toBe('c')
            ->and(fn () => (new MetaAdsListPostCommentsNode)->execute(metaAdsRun(), ['page_id' => '5', 'post_id' => '5/9'], []))->toThrow(InvalidArgumentException::class);
    });

    it('creates catalogs and lists products', function () {
        fakeMetaAds(['POST /3/owned_product_catalogs' => ['id' => '60'], 'GET /60/products' => ['data' => [['id' => 'p', 'name' => 'Shoe']]]]);

        expect((new MetaAdsCreateCatalogNode)->execute(metaAdsRun(), ['business_id' => '3', 'name' => 'Shop'], [])['id'])->toBe('60')
            ->and((new MetaAdsListProductsNode)->execute(metaAdsRun(), ['catalog_id' => '60'], [])['products'][0]['name'])->toBe('Shoe');
    });
});

describe('meta ads audiences', function () {
    it('creates audiences and lookalikes', function () {
        fakeMetaAds(['POST /act_1/customaudiences' => ['id' => '50']]);

        (new MetaAdsCreateAudienceNode)->execute(metaAdsRun(), ['account_id' => '1', 'name' => 'Buyers', 'customer_file_source' => 'USER_PROVIDED_ONLY'], []);

        expect(metaAdsParams('POST /act_1/customaudiences'))->toMatchArray(['subtype' => 'CUSTOM', 'customer_file_source' => 'USER_PROVIDED_ONLY']);

        fakeMetaAds(['POST /act_1/customaudiences' => ['id' => '51']]);

        (new MetaAdsCreateLookalikeNode)->execute(metaAdsRun(), ['account_id' => '1', 'name' => 'Like', 'origin_audience_id' => '50', 'country' => 'us', 'ratio' => '0.02'], []);

        expect(metaAdsParams('POST /act_1/customaudiences'))->toMatchArray(['subtype' => 'LOOKALIKE', 'origin_audience_id' => '50', 'lookalike_spec' => ['ratio' => 0.02, 'country' => 'US']]);
    });

    it('adds emails as hashes in one finished session, and removes them with the delete method', function () {
        fakeMetaAds(['POST /50/users' => ['num_received' => 1, 'num_invalid_entries' => 0, 'session_id' => '9']]);

        $added = (new MetaAdsAddEmailToAudienceNode)->execute(metaAdsRun(), ['audience_id' => '50', 'emails' => ['Mary@Example.com']], []);
        $params = metaAdsParams('POST /50/users');

        expect($added['num_received'])->toBe(1)
            ->and($params['payload'])->toBe(['schema' => 'EMAIL_SHA256', 'data' => [['f1904cf1a9d73a55fa5de0ac823c4403ded71afd4c3248d00bdcd0866552bb79']]])
            ->and($params['session'])->toMatchArray(['batch_seq' => 1, 'last_batch_flag' => true])
            ->and(metaAdsRequest('POST /50/users')->body())->not->toContain('example');

        fakeMetaAds(['POST /50/users' => ['num_received' => 1]]);

        (new MetaAdsRemoveEmailFromAudienceNode)->execute(metaAdsRun(), ['audience_id' => '50', 'emails' => ['mary@example.com']], []);

        expect(metaAdsParams('POST /50/users')['method'])->toBe('DELETE')
            ->and(metaAdsParams('POST /50/users'))->not->toHaveKey('session');
    });

    it('normalizes and hashes each column of a multi-key upload, leaving ids as they are', function () {
        fakeMetaAds(['POST /50/users' => ['num_received' => 1]]);

        (new MetaAdsAddPeopleToAudienceNode)->execute(metaAdsRun(), ['audience_id' => '50', 'schema' => ['email', 'phone', 'fn', 'extern_id'], 'rows' => [[' Ada@Acme.com ', '+1 (555) 000', "O'Brien", 'X-1']]], []);

        expect(metaAdsParams('POST /50/users')['payload'])->toBe(['schema' => ['EMAIL', 'PHONE', 'FN', 'EXTERN_ID'], 'data' => [[hash('sha256', 'ada@acme.com'), hash('sha256', '1555000'), hash('sha256', 'obrien'), 'X-1']]]);
    });
});

describe('meta ads leads and conversions', function () {
    it('lists forms with the Page token, and creates one', function () {
        fakeMetaAds(['GET /5' => ['access_token' => 'page-token'], 'GET /5/leadgen_forms' => ['data' => [['id' => '70', 'name' => 'Form']]], 'POST /5/leadgen_forms' => ['id' => '71']]);

        expect((new MetaAdsListLeadFormsNode)->execute(metaAdsRun(), ['page_id' => '5'], [])['forms'][0]['id'])->toBe('70')
            ->and(metaAdsRequest('GET /5/leadgen_forms')->header('Authorization')[0])->toBe('Bearer page-token')
            ->and((new MetaAdsCreateLeadFormNode)->execute(metaAdsRun(), ['page_id' => '5', 'name' => 'New', 'questions' => [['type' => 'EMAIL']]], [])['id'])->toBe('71')
            ->and(metaAdsParams('POST /5/leadgen_forms')['questions'])->toBe([['type' => 'EMAIL']]);
    });

    it('reads leads with their answers by field name', function () {
        fakeMetaAds(['GET /70/leads' => ['data' => [['id' => 'l1', 'field_data' => [['name' => 'email', 'values' => ['ada@acme.com']], ['name' => 'colors', 'values' => ['red', 'blue']]]]]], 'GET /1234' => ['id' => '1234', 'field_data' => [['name' => 'email', 'values' => ['x@y.z']]]]]);

        $output = (new MetaAdsListLeadsNode)->execute(metaAdsRun(), ['form_id' => '70', 'since' => '2026-01-01T00:00:00Z'], []);

        expect($output['leads'][0])->toBe(['id' => 'l1', 'fields' => ['email' => 'ada@acme.com', 'colors' => ['red', 'blue']]])
            ->and(metaAdsParams('GET /70/leads')['filtering'][0])->toMatchArray(['field' => 'time_created', 'operator' => 'GREATER_THAN', 'value' => strtotime('2026-01-01T00:00:00Z')])
            ->and((new MetaAdsGetLeadNode)->execute(metaAdsRun(), ['lead_id' => '1234'], [])['lead']['fields']['email'])->toBe('x@y.z');
    });

    it('sends conversions with personal data hashed, and needs a way to match', function () {
        fakeMetaAds(['POST /99/events' => ['events_received' => 1, 'fbtrace_id' => 'tr']]);

        $sent = (new MetaAdsSendPurchaseEventNode)->execute(metaAdsRun(), ['pixel_id' => '99', 'action_source' => 'website', 'email' => 'Ada@Acme.com', 'value' => '10.5', 'currency' => 'usd', 'client_ip_address' => '1.2.3.4', 'test_event_code' => 'T1'], []);
        $params = metaAdsParams('POST /99/events');

        expect($sent)->toMatchArray(['sent' => true, 'events_received' => 1])
            ->and($params['test_event_code'])->toBe('T1')
            ->and($params['data'][0])->toMatchArray(['event_name' => 'Purchase', 'action_source' => 'website', 'custom_data' => ['value' => '10.5', 'currency' => 'USD']])
            ->and($params['data'][0]['user_data'])->toBe(['em' => [hash('sha256', 'ada@acme.com')], 'client_ip_address' => '1.2.3.4'])
            ->and($params['data'][0]['event_time'])->toBeInt()
            ->and(fn () => (new MetaAdsSendConversionEventNode)->execute(metaAdsRun(), ['pixel_id' => '99', 'event_name' => 'X', 'action_source' => 'website'], []))->toThrow(InvalidArgumentException::class);
    });

    it('sends a funnel event by lead id, as coming from a CRM', function () {
        fakeMetaAds(['POST /99/events' => ['events_received' => 1]]);

        (new MetaAdsSendFunnelEventNode)->execute(metaAdsRun(), ['pixel_id' => '99', 'event_name' => 'Qualified', 'lead_id' => '1234', 'lead_event_source' => 'HubSpot'], []);

        expect(metaAdsParams('POST /99/events')['data'][0])->toMatchArray(['event_name' => 'Qualified', 'action_source' => 'system_generated', 'user_data' => ['lead_id' => '1234'], 'custom_data' => ['event_source' => 'crm', 'lead_event_source' => 'HubSpot']]);
    });
});

describe('meta ads options', function () {
    function metaAdsOptionsQuery(array $config = [], ?string $search = null): NodeOptionsQuery
    {
        return new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), $config, 'meta-token', $search);
    }

    it('lists accounts and what is in one, with a cursor', function () {
        fakeMetaAds(['GET /me/adaccounts' => ['data' => [['id' => 'act_1', 'name' => 'Main'], ['id' => 'act_2', 'name' => 'Other']], 'paging' => ['cursors' => ['after' => 'cur'], 'next' => 'https://x']], 'GET /act_1/campaigns' => ['data' => [['id' => '10', 'name' => 'Spring', 'effective_status' => 'ACTIVE']]]]);
        $source = app(MetaAdsOptions::class);
        $accounts = $source->load('meta_ads.accounts', metaAdsOptionsQuery([], 'main'))->toArray();

        expect($accounts['options'])->toBe([['value' => 'act_1', 'label' => 'Main']])
            ->and($accounts['next_cursor'])->toBe('cur')
            ->and(app(MetaAdsOptions::class)->load('meta_ads.campaigns', metaAdsOptionsQuery(['account_id' => 'act_1']))->toArray()['options'])->toBe([['value' => '10', 'label' => 'Spring', 'description' => 'ACTIVE']])
            ->and(fn () => app(MetaAdsOptions::class)->load('meta_ads.campaigns', metaAdsOptionsQuery(['account_id' => '../x'])))->toThrow(ConnectorException::class);
    });

    it('lists lead forms with the Page token', function () {
        fakeMetaAds(['GET /5' => ['access_token' => 'page-token'], 'GET /5/leadgen_forms' => ['data' => [['id' => '70', 'name' => 'Form', 'status' => 'ACTIVE']]]]);

        expect(app(MetaAdsOptions::class)->load('meta_ads.lead_forms', metaAdsOptionsQuery(['page_id' => '5']))->toArray()['options'][0]['value'])->toBe('70')
            ->and(metaAdsRequest('GET /5/leadgen_forms')->header('Authorization')[0])->toBe('Bearer page-token');
    });
});

describe('meta ads triggers', function () {
    it('keeps only campaigns in the window, newest first, and a change is a new event', function () {
        fakeMetaAds(['GET /act_1/campaigns' => ['data' => [['id' => '1', 'created_time' => '2020-01-01T00:00:00+0000', 'updated_time' => '2020-01-01T00:00:00+0000'], ['id' => '2', 'created_time' => now()->subMinute()->toIso8601String(), 'updated_time' => now()->subMinute()->toIso8601String()]]]]);

        expect(array_column((new MetaAdsListNewCampaignsNode)->execute(metaAdsRun(), ['account_id' => '1'], [])['records'], 'id'))->toBe(['2'])
            ->and((new MetaAdsListNewCampaignsNode)->triggerItemId(['id' => '2', 'updated_time' => 'T']))->toBe('2')
            ->and((new MetaAdsListChangedCampaignsNode)->triggerItemId(['id' => '2', 'updated_time' => 'T']))->toBe('2:T')
            ->and((new MetaAdsListNewAdSetsNode)->triggerDescription())->toContain('an ad set');
    });

    it('reads new leads of a form by time, or of every form of the Page', function () {
        fakeMetaAds(['GET /70/leads' => ['data' => [['id' => 'l1', 'field_data' => []]]], 'GET /71/leads' => ['data' => [['id' => 'l2', 'field_data' => []]]], 'GET /5' => ['access_token' => 'page-token'], 'GET /5/leadgen_forms' => ['data' => [['id' => '70'], ['id' => '71']]]]);
        $node = new MetaAdsListNewLeadsNode;

        expect(array_column($node->execute(metaAdsRun(), ['page_id' => '5'], [])['leads'], 'id'))->toBe(['l1', 'l2'])
            ->and(array_column($node->execute(metaAdsRun(), ['page_id' => '5', 'form_id' => '70'], [])['leads'], 'id'))->toBe(['l1'])
            ->and(metaAdsParams('GET /70/leads')['filtering'][0]['field'])->toBe('time_created')
            ->and($node->triggerName())->toBe('Meta Ads: New lead');
    });

    it('polls new leads: a baseline first, then one run for each new one', function () {
        Queue::fake([FireTriggerEvent::class]);
        fakeMetaAds([]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'meta_ads') ?? Connector::factory()->create(['key' => 'meta_ads']))
            ->create(['data' => ['access_token' => 'meta-token'], 'created_by' => $owner->id]);
        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'meta_ads_list_new_leads', 'config' => ['credential_id' => $credential->id, 'page_id' => '5', 'form_id' => '70', 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);
        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        SyncTriggerSubscription::dispatchSync($trigger->id);
        $poll = fn (array $ids) => fakeMetaAds(['GET /70/leads' => ['data' => array_map(fn (string $id): array => ['id' => $id], $ids)]]);

        $poll(['1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['2', '1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->values()->all())->toBe(['2']);
    });
});

it('makes any API call, takes GET, POST and DELETE only, and refuses a path that leaves the host', function () {
    fakeMetaAds(['POST /act_1/campaigns' => ['id' => '9']]);

    $node = app(NodeRegistry::class)->resolve('meta_ads_api_request');
    $run = metaAdsRun();
    $output = $node->execute($run, ['method' => 'POST', 'path' => '/act_1/campaigns', 'query' => ['name' => 'Spring', 'status' => 'PAUSED']], []);

    expect($output)->toBe(['status' => 200, 'body' => ['id' => '9']])
        ->and(metaAdsParams('POST /act_1/campaigns'))->toMatchArray(['name' => 'Spring', 'status' => 'PAUSED'])
        ->and($node->effect(['method' => 'GET'])->value)->toBe('external');

    foreach (['https://evil.example/x', '/../x', '/a?b=1'] as $path) {
        expect(fn () => $node->execute($run, ['method' => 'GET', 'path' => $path], []))->toThrow(InvalidArgumentException::class);
    }

    expect(fn () => $node->execute($run, ['method' => 'PUT', 'path' => '/a'], []))->toThrow(InvalidArgumentException::class);
});
