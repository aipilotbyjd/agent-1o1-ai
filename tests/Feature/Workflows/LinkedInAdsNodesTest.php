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
use App\Nodes\Integrations\LinkedInAds\AbstractLinkedInAdsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsAddAccountUserNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsAddCompanyToAudienceNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsAddContactToAudienceNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsAddConversionToCampaignNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateAudienceNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateCampaignGroupNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateCampaignNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateConversionNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateCreativeNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateInlineCreativeNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsCreateLeadFormNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsDeleteCampaignNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsGetAnalyticsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsGetCampaignNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsGetStatisticsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListChangedCampaignsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListLeadsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListMyAccountsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListNewCampaignsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListNewEventLeadsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListNewLeadFormsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsListNewLeadsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsRemoveContactFromAudienceNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsSearchCampaignsNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsSearchCreativesNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsSendConversionEventNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsSetCampaignStatusNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsUpdateCampaignNode;
use App\Nodes\Integrations\LinkedInAds\LinkedInAdsUpdateCreativeNode;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\LinkedInAdsOptions;
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

function linkedinAdsRun(): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'linkedin_ads') ?? Connector::factory()->create(['key' => 'linkedin_ads']))->id,
        'data' => ['access_token' => 'li-token'],
        'is_default' => true,
    ]);

    return $run;
}

function linkedinAdsKey(Request $request): string
{
    return $request->method().' '.preg_replace('~^/rest~', '', rawurldecode((string) parse_url($request->url(), PHP_URL_PATH)));
}

/**
 * Answer LinkedIn. A key is "METHOD /path" under /rest.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeLinkedinAds(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.linkedin.com/*' => function (Request $request) use ($routes) {
        $key = linkedinAdsKey($request);
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['message' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function linkedinAdsRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => linkedinAdsKey($pair[0]) === $route));

    expect($found)->not->toBeEmpty();

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function linkedinAdsBody(string $route): array
{
    return (array) json_decode(linkedinAdsRequest($route)->body(), true);
}

/**
 * A created answer: LinkedIn puts the id in a header.
 */
function linkedinAdsCreated(string $id): PromiseInterface
{
    return Http::response('', 201, ['x-restli-id' => $id]);
}

describe('linkedin ads registry and connector', function () {
    it('registers every node under the linkedin_ads connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'linkedin_ads_'));

        expect(count($types))->toBeGreaterThanOrEqual(60);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractLinkedInAdsNode::class)->and($registry->resolve($type)->category())->toBe('linkedin_ads');
        }
    });

    it('seeds the connector with its login and the node category', function () {
        $this->seed([ConnectorSeeder::class, AppNodeCategorySeeder::class]);

        $connector = Connector::query()->where('key', 'linkedin_ads')->firstOrFail();

        expect($connector->oauth['authorize_url'])->toBe('https://www.linkedin.com/oauth/v2/authorization')
            ->and($connector->oauth['scopes'])->toContain('rw_ads', 'r_ads_reporting', 'r_marketing_leadgen_automation', 'rw_dmp_segments', 'rw_conversions');
    });

    it('checks the connection through the ad accounts of the member', function () {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector(Connector::factory()->create(['key' => 'linkedin_ads', 'name' => 'LinkedIn Ads']))->create(['data' => ['access_token' => 'li-token'], 'created_by' => $owner->id]);

        fakeLinkedinAds(['GET /adAccountUsers' => ['elements' => [['account' => 'urn:li:sponsoredAccount:1']]]]);
        $good = app(ConnectorCredentialTester::class)->test($credential);

        expect($good['ok'])->toBeTrue()->and($good['account'])->toBe('1 ad accounts')
            ->and(linkedinAdsRequest('GET /adAccountUsers')->header('Linkedin-Version')[0])->toBe('202610');

        fakeLinkedinAds(['GET /adAccountUsers' => fn () => Http::response(['message' => 'no'], 401)]);

        expect(app(ConnectorCredentialTester::class)->test($credential)['ok'])->toBeFalse();
    });
});

describe('linkedin ads requests', function () {
    it('sends the version and protocol headers, and keeps Rest.li brackets while encoding URNs', function () {
        fakeLinkedinAds(['GET /adAccounts/123/adCampaigns' => ['elements' => [['id' => 9, 'name' => 'C', 'status' => 'ACTIVE']], 'metadata' => ['nextPageToken' => 'tok']]]);

        $output = (new LinkedInAdsSearchCampaignsNode)->execute(linkedinAdsRun(), ['account_id' => 'urn:li:sponsoredAccount:123', 'status' => 'ACTIVE', 'campaign_group_id' => '77', 'limit' => 10], []);
        $request = linkedinAdsRequest('GET /adAccounts/123/adCampaigns');

        expect($output)->toMatchArray(['count' => 1, 'next_page_token' => 'tok'])
            ->and($output['campaigns'][0])->toMatchArray(['id' => '9', 'status' => 'ACTIVE'])
            ->and($request->header('Linkedin-Version')[0])->toBe('202610')
            ->and($request->header('X-Restli-Protocol-Version')[0])->toBe('2.0.0')
            ->and($request->url())->toContain('search=(campaignGroup:(values:List(urn%3Ali%3AsponsoredCampaignGroup%3A77)),status:(values:List(ACTIVE)))')
            ->and($request->url())->toContain('sortOrder=DESCENDING')->toContain('pageSize=10');
    });

    it('refuses an id that cannot sit in a URL', function () {
        fakeLinkedinAds([]);

        expect(fn () => (new LinkedInAdsGetCampaignNode)->execute(linkedinAdsRun(), ['account_id' => '1/../2', 'campaign_id' => '5'], []))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new LinkedInAdsGetCampaignNode)->execute(linkedinAdsRun(), ['account_id' => '1', 'campaign_id' => 'abc'], []))->toThrow(InvalidArgumentException::class);
    });

    it('explains errors with a hint', function (int $status, string $expected) {
        fakeLinkedinAds(['GET /adAccountUsers' => fn () => Http::response(['message' => 'Refused'], $status)]);

        expect(fn () => (new LinkedInAdsListMyAccountsNode)->execute(linkedinAdsRun(), [], []))->toThrow(RuntimeException::class, $expected);
    })->with([[401, 'Reconnect LinkedIn Ads'], [403, 'API program'], [429, 'rate limiting']]);
});

describe('linkedin ads money safety', function () {
    it('creates a campaign paused unless told otherwise, and declares a live one as external', function () {
        fakeLinkedinAds(['POST /adAccounts/1/adCampaigns' => fn () => linkedinAdsCreated('555')]);
        $node = new LinkedInAdsCreateCampaignNode;

        $output = $node->execute(linkedinAdsRun(), ['account_id' => '1', 'campaign_group_id' => '2', 'name' => 'Spring', 'type' => 'SPONSORED_UPDATES', 'cost_type' => 'CPC', 'daily_budget' => '50', 'currency' => 'usd', 'locale_country' => 'us', 'locale_language' => 'EN', 'targeting_criteria' => ['include' => ['and' => []]]], []);
        $body = linkedinAdsBody('POST /adAccounts/1/adCampaigns');

        expect($output)->toBe(['created' => true, 'id' => '555', 'status' => 'PAUSED'])
            ->and($body)->toMatchArray(['status' => 'PAUSED', 'account' => 'urn:li:sponsoredAccount:1', 'campaignGroup' => 'urn:li:sponsoredCampaignGroup:2', 'dailyBudget' => ['amount' => '50', 'currencyCode' => 'USD'], 'locale' => ['country' => 'US', 'language' => 'en']])
            ->and($body['runSchedule']['start'])->toBeInt()
            ->and($node->effect([]))->toBe(ActionEffect::Write)
            ->and($node->effect(['status' => 'PAUSED']))->toBe(ActionEffect::Write)
            ->and($node->effect(['status' => 'ACTIVE']))->toBe(ActionEffect::External)
            ->and($node->effect(['status' => '{{ steps.a.status }}']))->toBe(ActionEffect::External);
    });

    it('wants a budget on a new campaign', function () {
        fakeLinkedinAds([]);

        expect(fn () => (new LinkedInAdsCreateCampaignNode)->execute(linkedinAdsRun(), ['account_id' => '1', 'campaign_group_id' => '2', 'name' => 'S', 'type' => 'TEXT_AD', 'cost_type' => 'CPC', 'locale_country' => 'US', 'locale_language' => 'en', 'targeting_criteria' => ['a' => 1]], []))->toThrow(InvalidArgumentException::class, 'budget');
    });

    it('creates groups and creatives as drafts', function () {
        fakeLinkedinAds(['POST /adAccounts/1/adCampaignGroups' => fn () => linkedinAdsCreated('7'), 'POST /adAccounts/1/creatives' => fn () => linkedinAdsCreated('urn:li:sponsoredCreative:8')]);

        $group = (new LinkedInAdsCreateCampaignGroupNode)->execute(linkedinAdsRun(), ['account_id' => '1', 'name' => 'G'], []);
        $creative = (new LinkedInAdsCreateCreativeNode)->execute(linkedinAdsRun(), ['account_id' => '1', 'campaign_id' => '3', 'content_reference' => 'urn:li:ugcPost:44'], []);

        expect($group['status'])->toBe('DRAFT')
            ->and(linkedinAdsBody('POST /adAccounts/1/adCampaignGroups')['status'])->toBe('DRAFT')
            ->and($creative['id'])->toBe('urn:li:sponsoredCreative:8')
            ->and(linkedinAdsBody('POST /adAccounts/1/creatives'))->toMatchArray(['intendedStatus' => 'DRAFT', 'content' => ['reference' => 'urn:li:ugcPost:44'], 'campaign' => 'urn:li:sponsoredCampaign:3'])
            ->and((new LinkedInAdsCreateCreativeNode)->effect([]))->toBe(ActionEffect::Write)
            ->and((new LinkedInAdsCreateCreativeNode)->effect(['intended_status' => 'ACTIVE']))->toBe(ActionEffect::External);
    });

    it('creates an inline creative through its action, wrapped as LinkedIn wants', function () {
        fakeLinkedinAds(['POST /adAccounts/1/creatives' => ['value' => ['creative' => 'urn:li:sponsoredCreative:9']]]);

        $output = (new LinkedInAdsCreateInlineCreativeNode)->execute(linkedinAdsRun(), ['account_id' => '1', 'campaign_id' => '3', 'inline_content' => ['post' => ['commentary' => 'Hi']]], []);

        expect($output['id'])->toBe('urn:li:sponsoredCreative:9')
            ->and(linkedinAdsRequest('POST /adAccounts/1/creatives')->url())->toContain('action=createInline')
            ->and(linkedinAdsBody('POST /adAccounts/1/creatives')['creative'])->toMatchArray(['intendedStatus' => 'DRAFT', 'inlineContent' => ['post' => ['commentary' => 'Hi']]]);
    });

    it('patches a status with the partial update method, and declares it by what it does', function () {
        fakeLinkedinAds(['POST /adAccounts/1/adCampaigns/5' => fn () => Http::response('', 204)]);
        $node = new LinkedInAdsSetCampaignStatusNode;

        $output = $node->execute(linkedinAdsRun(), ['account_id' => '1', 'campaign_id' => '5', 'status' => 'active'], []);

        expect($output['status'])->toBe('ACTIVE')
            ->and(linkedinAdsBody('POST /adAccounts/1/adCampaigns/5'))->toBe(['patch' => ['$set' => ['status' => 'ACTIVE']]])
            ->and(linkedinAdsRequest('POST /adAccounts/1/adCampaigns/5')->header('X-RestLi-Method')[0])->toBe('PARTIAL_UPDATE')
            ->and($node->effect(['status' => 'ACTIVE']))->toBe(ActionEffect::External)
            ->and($node->effect(['status' => 'PAUSED']))->toBe(ActionEffect::Write);
    });

    it('declares a budget change as spending, and wants something to change', function () {
        fakeLinkedinAds([]);
        $node = new LinkedInAdsUpdateCampaignNode;

        expect($node->effect(['daily_budget' => '100']))->toBe(ActionEffect::External)
            ->and($node->effect(['name' => 'X']))->toBe(ActionEffect::Write)
            ->and(fn () => $node->execute(linkedinAdsRun(), ['account_id' => '1', 'campaign_id' => '5'], []))->toThrow(InvalidArgumentException::class, 'at least one');
    });

    it('deletes a draft, and only marks a live campaign for deletion', function () {
        fakeLinkedinAds(['GET /adAccounts/1/adCampaigns/5' => ['status' => 'DRAFT'], 'DELETE /adAccounts/1/adCampaigns/5' => fn () => Http::response('', 204), 'GET /adAccounts/1/adCampaigns/6' => ['status' => 'ACTIVE'], 'POST /adAccounts/1/adCampaigns/6' => fn () => Http::response('', 204)]);
        $node = new LinkedInAdsDeleteCampaignNode;

        expect($node->execute(linkedinAdsRun(), ['account_id' => '1', 'campaign_id' => '5'], [])['result'])->toBe('deleted')
            ->and($node->execute(linkedinAdsRun(), ['account_id' => '1', 'campaign_id' => '6'], [])['result'])->toBe('pending_deletion')
            ->and(linkedinAdsBody('POST /adAccounts/1/adCampaigns/6')['patch']['$set']['status'])->toBe('PENDING_DELETION')
            ->and($node->effect([]))->toBe(ActionEffect::Destructive);
    });

    it('updates a creative by its encoded URN', function () {
        fakeLinkedinAds(['POST /adAccounts/1/creatives/urn:li:sponsoredCreative:8' => fn () => Http::response('', 204)]);

        (new LinkedInAdsUpdateCreativeNode)->execute(linkedinAdsRun(), ['account_id' => '1', 'creative_id' => '8', 'intended_status' => 'paused'], []);

        expect(linkedinAdsRequest('POST /adAccounts/1/creatives/urn:li:sponsoredCreative:8')->url())->toContain('creatives/urn%3Ali%3AsponsoredCreative%3A8')
            ->and(linkedinAdsBody('POST /adAccounts/1/creatives/urn:li:sponsoredCreative:8')['patch']['$set'])->toBe(['intendedStatus' => 'PAUSED']);
    });

    it('gives a member access and declares it as reaching outside', function () {
        fakeLinkedinAds(['PUT /adAccountUsers/*' => fn () => Http::response('', 201)]);
        $node = new LinkedInAdsAddAccountUserNode;

        $node->execute(linkedinAdsRun(), ['account_id' => '1', 'user' => 'urn:li:person:abc', 'role' => 'VIEWER'], []);

        expect(Http::recorded()->first()[0]->url())->toContain('/adAccountUsers/(account:urn%3Ali%3AsponsoredAccount%3A1,user:urn%3Ali%3Aperson%3Aabc)')
            ->and($node->effect([]))->toBe(ActionEffect::External);
    });
});

describe('linkedin ads reports, leads, audiences, conversions', function () {
    it('reports by one breakdown with the date range and facets unencoded where Rest.li needs it', function () {
        fakeLinkedinAds(['GET /adAnalytics' => ['elements' => [['impressions' => 10, 'pivotValues' => ['urn:li:sponsoredCampaign:5']]]]]);

        $output = (new LinkedInAdsGetAnalyticsNode)->execute(linkedinAdsRun(), ['pivot' => 'CAMPAIGN', 'account_ids' => ['1'], 'date_from' => '2026-03-02', 'date_to' => '2026-03-04', 'time_granularity' => 'DAILY', 'fields' => ['impressions']], []);
        $url = linkedinAdsRequest('GET /adAnalytics')->url();

        expect($output['rows'][0]['impressions'])->toBe(10)
            ->and($url)->toContain('q=analytics&pivot=CAMPAIGN')
            ->and($url)->toContain('dateRange=(start:(year:2026,month:3,day:2),end:(year:2026,month:3,day:4))')
            ->and($url)->toContain('accounts=List(urn%3Ali%3AsponsoredAccount%3A1)')
            ->and($url)->toContain('timeGranularity=DAILY')
            ->and($url)->toContain('fields=impressions,dateRange,pivotValues');
    });

    it('reports by several breakdowns, and wants a facet', function () {
        fakeLinkedinAds(['GET /adAnalytics' => ['elements' => []]]);

        (new LinkedInAdsGetStatisticsNode)->execute(linkedinAdsRun(), ['pivots' => ['CAMPAIGN', 'CREATIVE'], 'campaign_ids' => ['5']], []);

        expect(linkedinAdsRequest('GET /adAnalytics')->url())->toContain('q=statistics&pivots=List(CAMPAIGN,CREATIVE)')
            ->and(fn () => (new LinkedInAdsGetAnalyticsNode)->execute(linkedinAdsRun(), ['pivot' => 'CAMPAIGN'], []))->toThrow(InvalidArgumentException::class, 'at least one');
    });

    it('reads leads of an owner, flattening the answers', function () {
        fakeLinkedinAds(['GET /leadFormResponses' => ['elements' => [['id' => 'lead-1', 'leadType' => 'SPONSORED', 'submittedAt' => 1686182358881, 'versionedLeadGenFormUrn' => 'urn:li:versionedLeadGenForm:(urn:li:leadGenForm:3162,1)', 'leadMetadata' => ['sponsoredLeadMetadata' => ['campaign' => 'urn:li:sponsoredCampaign:5']], 'formResponse' => ['answers' => [['questionId' => 1, 'answerDetails' => ['textQuestionAnswer' => ['answer' => 'Ada']]], ['questionId' => 2, 'answerDetails' => ['multipleChoiceAnswer' => ['options' => [1, 3]]]]]]]]]]);

        $output = (new LinkedInAdsListLeadsNode)->execute(linkedinAdsRun(), ['account_id' => '1', 'lead_type' => 'SPONSORED', 'form_id' => '3162'], []);
        $url = linkedinAdsRequest('GET /leadFormResponses')->url();

        expect($output['leads'][0])->toMatchArray(['id' => 'lead-1', 'campaign' => 'urn:li:sponsoredCampaign:5'])
            ->and($output['leads'][0]['answers'])->toBe([['question_id' => 1, 'text' => 'Ada', 'options' => null], ['question_id' => 2, 'text' => null, 'options' => [1, 3]]])
            ->and($url)->toContain('q=owner&owner=(sponsoredAccount:urn%3Ali%3AsponsoredAccount%3A1)&leadType=(leadType:SPONSORED)')
            ->and($url)->toContain('versionedLeadGenFormUrn=urn%3Ali%3AversionedLeadGenForm%3A%28urn%3Ali%3AleadGenForm%3A3162%2C1%29');
    });

    it('creates a lead form owned by a company page', function () {
        fakeLinkedinAds(['POST /leadForms' => fn () => linkedinAdsCreated('66')]);

        $output = (new LinkedInAdsCreateLeadFormNode)->execute(linkedinAdsRun(), ['organization_id' => '9', 'name' => 'Form', 'content' => ['questions' => []], 'state' => 'draft'], []);

        expect($output['id'])->toBe('66')->and(linkedinAdsBody('POST /leadForms'))->toMatchArray(['owner' => ['organization' => 'urn:li:organization:9'], 'state' => 'DRAFT']);
    });

    it('creates an audience, and adds and removes people with their email hashed', function () {
        fakeLinkedinAds(['POST /dmpSegments' => fn () => linkedinAdsCreated('10804'), 'POST /dmpSegments/10804/users' => fn () => Http::response('', 201), 'POST /dmpSegments/10804/companies' => fn () => Http::response('', 201)]);
        $hash = hash('sha256', 'ada@acme.com');

        $created = (new LinkedInAdsCreateAudienceNode)->execute(linkedinAdsRun(), ['account_id' => '1', 'name' => 'A', 'type' => 'USER'], []);
        (new LinkedInAdsAddContactToAudienceNode)->execute(linkedinAdsRun(), ['segment_id' => '10804', 'email' => ' Ada@Acme.com '], []);

        expect($created['id'])->toBe('10804')
            ->and(linkedinAdsBody('POST /dmpSegments'))->toMatchArray(['sourcePlatform' => 'DIRECT_API', 'destinations' => [['destination' => 'LINKEDIN']]])
            ->and(linkedinAdsBody('POST /dmpSegments/10804/users'))->toBe(['action' => 'ADD', 'userIds' => [['idType' => 'SHA256_EMAIL', 'idValue' => $hash]]])
            ->and(linkedinAdsRequest('POST /dmpSegments/10804/users')->body())->not->toContain('acme');

        fakeLinkedinAds(['POST /dmpSegments/10804/users' => fn () => Http::response('', 201), 'POST /dmpSegments/10804/companies' => fn () => Http::response('', 201)]);

        (new LinkedInAdsRemoveContactFromAudienceNode)->execute(linkedinAdsRun(), ['segment_id' => '10804', 'email' => 'ada@acme.com'], []);
        (new LinkedInAdsAddCompanyToAudienceNode)->execute(linkedinAdsRun(), ['segment_id' => '10804', 'company_name' => 'Acme', 'company_website_domain' => 'acme.com'], []);

        expect(linkedinAdsBody('POST /dmpSegments/10804/users')['action'])->toBe('REMOVE')
            ->and(linkedinAdsBody('POST /dmpSegments/10804/companies'))->toBe(['action' => 'ADD', 'companyName' => 'Acme', 'companyWebsiteDomain' => 'acme.com'])
            ->and(fn () => (new LinkedInAdsAddContactToAudienceNode)->execute(linkedinAdsRun(), ['segment_id' => '10804'], []))->toThrow(InvalidArgumentException::class);
    });

    it('creates a conversion rule, ties it to a campaign and sends an event', function () {
        fakeLinkedinAds(['POST /conversions' => fn () => linkedinAdsCreated('70'), 'PUT /campaignConversions/*' => fn () => Http::response('', 204), 'POST /conversionEvents' => fn () => Http::response('', 201)]);

        $rule = (new LinkedInAdsCreateConversionNode)->execute(linkedinAdsRun(), ['account_id' => '1', 'name' => 'Lead', 'type' => 'LEAD', 'auto_association' => 'ALL_CAMPAIGNS'], []);
        $link = (new LinkedInAdsAddConversionToCampaignNode)->execute(linkedinAdsRun(), ['account_id' => '1', 'campaign_id' => '5', 'conversion_id' => '70'], []);
        $sent = (new LinkedInAdsSendConversionEventNode)->execute(linkedinAdsRun(), ['account_id' => '1', 'conversion_id' => '70', 'email' => 'ada@acme.com', 'value' => '50', 'currency' => 'usd', 'happened_at' => '2026-10-01T00:00:00Z'], []);
        $event = linkedinAdsBody('POST /conversionEvents');

        expect($rule['id'])->toBe('70')
            ->and(linkedinAdsRequest('POST /conversions')->url())->toContain('autoAssociationType=ALL_CAMPAIGNS')
            ->and($link['conversion'])->toBe('urn:lla:llaPartnerConversion:70')
            ->and($sent['sent'])->toBeTrue()
            ->and($event['conversion'])->toBe('urn:lla:llaPartnerConversion:70')
            ->and($event['conversionValue'])->toBe(['currencyCode' => 'USD', 'amount' => '50'])
            ->and($event['conversionHappenedAt'])->toBe(strtotime('2026-10-01T00:00:00Z') * 1000)
            ->and($event['user']['userIds'][0])->toBe(['idType' => 'SHA256_EMAIL', 'idValue' => hash('sha256', 'ada@acme.com')])
            ->and(fn () => (new LinkedInAdsSendConversionEventNode)->execute(linkedinAdsRun(), ['account_id' => '1', 'conversion_id' => '70'], []))->toThrow(InvalidArgumentException::class);
    });

    it('lists creatives by campaign', function () {
        fakeLinkedinAds(['GET /adAccounts/1/creatives' => ['elements' => [['id' => 'urn:li:sponsoredCreative:8', 'intendedStatus' => 'ACTIVE']]]]);

        $output = (new LinkedInAdsSearchCreativesNode)->execute(linkedinAdsRun(), ['account_id' => '1', 'campaign_id' => '5', 'intended_status' => 'ACTIVE'], []);

        expect($output['creatives'][0]['intended_status'])->toBe('ACTIVE')
            ->and(linkedinAdsRequest('GET /adAccounts/1/creatives')->url())->toContain('q=criteria')->toContain('campaigns=List(urn%3Ali%3AsponsoredCampaign%3A5)')->toContain('intendedStatuses=List(ACTIVE)');
    });
});

describe('linkedin ads options', function () {
    function linkedinAdsOptionsQuery(array $config = [], ?string $search = null, ?string $cursor = null): NodeOptionsQuery
    {
        return new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), $config, 'li-token', $search, $cursor);
    }

    it('lists accounts, then what is in one, and pages by token', function () {
        fakeLinkedinAds(['GET /adAccounts' => ['elements' => [['id' => 1, 'name' => 'Main', 'status' => 'ACTIVE'], ['id' => 2, 'name' => 'Other']], 'metadata' => ['nextPageToken' => 'next']], 'GET /adAccounts/1/adCampaigns' => ['elements' => [['id' => 5, 'name' => 'Spring']]], 'GET /dmpSegments' => ['elements' => [['id' => 9, 'name' => 'Aud']]]]);
        $source = app(LinkedInAdsOptions::class);

        $accounts = $source->load('linkedin_ads.accounts', linkedinAdsOptionsQuery([], 'main'))->toArray();

        expect($accounts['options'])->toBe([['value' => '1', 'label' => 'Main', 'description' => 'ACTIVE']])
            ->and($accounts['next_cursor'])->toBe('next')
            ->and(linkedinAdsRequest('GET /adAccounts')->url())->toContain('search=(status:(values:List(ACTIVE,DRAFT)))')
            ->and($source->load('linkedin_ads.campaigns', linkedinAdsOptionsQuery(['account_id' => '1']))->toArray()['options'][0]['label'])->toBe('Spring')
            ->and($source->load('linkedin_ads.segments', linkedinAdsOptionsQuery(['account_id' => '1']))->toArray()['options'][0]['value'])->toBe('9');
    });
});

describe('linkedin ads triggers', function () {
    it('reads new campaigns newest first and only those in the window, with a change as a new event', function () {
        fakeLinkedinAds(['GET /adAccounts/1/adCampaigns' => ['elements' => [['id' => 2, 'changeAuditStamps' => ['created' => ['time' => now()->getTimestampMs()], 'lastModified' => ['time' => 111]]], ['id' => 1, 'changeAuditStamps' => ['created' => ['time' => 5000], 'lastModified' => ['time' => 222]]]]]]);

        $output = (new LinkedInAdsListNewCampaignsNode)->execute(linkedinAdsRun(), ['account_id' => '1'], []);

        expect(array_column($output['records'], 'id'))->toBe(['2'])
            ->and(linkedinAdsRequest('GET /adAccounts/1/adCampaigns')->url())->toContain('q=search&sortOrder=DESCENDING')
            ->and((new LinkedInAdsListNewCampaignsNode)->triggerItemId(['id' => '2', 'updated_at_ms' => 9]))->toBe('2')
            ->and((new LinkedInAdsListChangedCampaignsNode)->triggerItemId(['id' => '2', 'updated_at_ms' => 9]))->toBe('2:9');
    });

    it('reads new leads by submission time and names the answers for the run', function () {
        fakeLinkedinAds(['GET /leadFormResponses' => ['elements' => [['id' => 'lead-1', 'versionedLeadGenFormUrn' => 'urn:li:versionedLeadGenForm:(urn:li:leadGenForm:3162,1)', 'formResponse' => ['answers' => [['questionId' => 1, 'answerDetails' => ['textQuestionAnswer' => ['answer' => 'Ada']]]]]]]], 'GET /leadForms/3162' => ['content' => ['questions' => [['questionId' => 1, 'name' => 'firstName']]]]]);
        $node = new LinkedInAdsListNewLeadsNode;
        $run = linkedinAdsRun();

        $lead = $node->execute($run, ['account_id' => '1'], [])['leads'][0];
        $url = linkedinAdsRequest('GET /leadFormResponses')->url();

        expect($url)->toContain('leadType=(leadType:SPONSORED)')->toContain('submittedAtTimeRange=(start:')
            ->and($node->expandTriggerItem($run, ['account_id' => '1'], $lead)['fields'])->toBe(['firstName' => 'Ada'])
            ->and((new LinkedInAdsListNewEventLeadsNode)->triggerName())->toBe('LinkedIn Ads: New event registration')
            ->and($node->triggerName())->toBe('LinkedIn Ads: New ad lead');
    });

    it('keeps a lead when its form cannot be read', function () {
        fakeLinkedinAds(['GET /leadForms/3162' => fn () => Http::response(['message' => 'No'], 403)]);
        $lead = ['id' => 'lead-1', 'form' => 'urn:li:versionedLeadGenForm:(urn:li:leadGenForm:3162,1)'];

        expect((new LinkedInAdsListNewLeadsNode)->expandTriggerItem(linkedinAdsRun(), [], $lead))->toBe($lead);
    });

    it('lists new lead forms newest first', function () {
        fakeLinkedinAds(['GET /leadForms' => ['elements' => [['id' => 1, 'created' => 10], ['id' => 2, 'created' => 20]]]]);

        expect(array_column((new LinkedInAdsListNewLeadFormsNode)->execute(linkedinAdsRun(), ['account_id' => '1'], [])['forms'], 'id'))->toBe([2, 1]);
    });

    it('polls ad leads: a baseline first, then one run for each new one', function () {
        Queue::fake([FireTriggerEvent::class]);
        fakeLinkedinAds([]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'linkedin_ads') ?? Connector::factory()->create(['key' => 'linkedin_ads']))
            ->create(['data' => ['access_token' => 'li-token'], 'created_by' => $owner->id]);
        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'linkedin_ads_list_new_leads', 'config' => ['credential_id' => $credential->id, 'account_id' => '1', 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);
        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        SyncTriggerSubscription::dispatchSync($trigger->id);
        $poll = fn (array $ids) => fakeLinkedinAds(['GET /leadFormResponses' => ['elements' => array_map(fn (string $id): array => ['id' => $id], $ids)]]);

        $poll(['a']);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['b', 'a']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->values()->all())->toBe(['b']);
    });
});

it('makes any API call with the query kept as written, and refuses a path that leaves the host', function () {
    fakeLinkedinAds(['GET /adAccounts/123/adCampaigns' => ['elements' => []]]);

    $node = app(NodeRegistry::class)->resolve('linkedin_ads_api_request');
    $run = linkedinAdsRun();
    $output = $node->execute($run, ['method' => 'GET', 'path' => '/adAccounts/123/adCampaigns', 'query' => 'q=search&search=(status:(values:List(ACTIVE)))'], []);
    $request = linkedinAdsRequest('GET /adAccounts/123/adCampaigns');

    expect($output)->toBe(['status' => 200, 'body' => ['elements' => []]])
        ->and(rawurldecode($request->url()))->toContain('search=(status:(values:List(ACTIVE)))')
        ->and($request->hasHeader('Linkedin-Version'))->toBeTrue()
        ->and($node->effect([])->value)->toBe('external');

    foreach (['https://evil.example/x', '/../x', '/a?b=1'] as $path) {
        expect(fn () => $node->execute($run, ['method' => 'GET', 'path' => $path], []))->toThrow(InvalidArgumentException::class);
    }

    expect(fn () => $node->execute($run, ['method' => 'GET', 'path' => '/a', 'query' => 'a=1#b'], []))->toThrow(InvalidArgumentException::class);
});
