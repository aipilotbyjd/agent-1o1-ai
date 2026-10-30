<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\LinkedIn\AbstractLinkedInNode;
use App\Nodes\Integrations\LinkedIn\LinkedInCommentOnPostNode;
use App\Nodes\Integrations\LinkedIn\LinkedInCreateImagePostNode;
use App\Nodes\Integrations\LinkedIn\LinkedInCreateLinkPostNode;
use App\Nodes\Integrations\LinkedIn\LinkedInCreateTextPostNode;
use App\Nodes\Integrations\LinkedIn\LinkedInDeletePostNode;
use App\Nodes\Integrations\LinkedIn\LinkedInGetPostNode;
use App\Nodes\Integrations\LinkedIn\LinkedInLikePostNode;
use App\Nodes\Integrations\LinkedIn\LinkedInListPagesNode;
use App\Nodes\Integrations\LinkedIn\LinkedInListPostsNode;
use App\Services\Http\GuardedHttp;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\LinkedInOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const LINKEDIN_POST = 'urn:li:share:7000000000000000001';

function linkedinRun(): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'linkedin') ?? Connector::factory()->create(['key' => 'linkedin']))->id,
        'data' => ['api_key' => 'li_token'],
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer LinkedIn. A key is "METHOD /path" (decoded, so a URN reads as written); the
 * value is the JSON body, or a closure returning a response. `GET /v2/userinfo` is
 * answered unless a test says otherwise. A route nobody answers is a 404.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeLinkedIn(array $routes): void
{
    $routes = ['GET /v2/userinfo' => ['sub' => 'abc123', 'name' => 'Ada Lovelace', 'email' => 'ada@acme.com'], ...$routes];

    Http::swap(new Factory);
    Http::fake(['api.linkedin.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.rawurldecode((string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['status' => 404, 'message' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function linkedinRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.rawurldecode((string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function linkedinBody(string $route): array
{
    return (array) json_decode(linkedinRequest($route)->body(), true);
}

/**
 * @return array<string, string>
 */
function linkedinQuery(string $route): array
{
    parse_str((string) parse_url(linkedinRequest($route)->url(), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function linkedinPost(string $id = LINKEDIN_POST, array $extra = []): array
{
    return ['id' => $id, 'author' => 'urn:li:person:abc123', 'commentary' => 'Hello', 'visibility' => 'PUBLIC', 'lifecycleState' => 'PUBLISHED', 'createdAt' => 1772000000000, 'publishedAt' => 1772000000000, ...$extra];
}

describe('linkedin nodes', function () {
    it('registers the nodes under the linkedin connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'linkedin_'));

        expect(count($types))->toBeGreaterThanOrEqual(12);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractLinkedInNode::class)->and($registry->resolve($type)->category())->toBe('linkedin');
        }
    });

    it('posts as the member with the version headers, reading the new id from the header', function () {
        fakeLinkedIn(['POST /rest/posts' => fn () => Http::response(null, 201, ['x-restli-id' => LINKEDIN_POST])]);

        $output = (new LinkedInCreateTextPostNode)->execute(linkedinRun(), ['text' => 'Launch day (finally) #win'], []);

        $request = linkedinRequest('POST /rest/posts');

        expect($output)->toBe(['posted' => true, 'id' => LINKEDIN_POST, 'author' => 'urn:li:person:abc123', 'url' => 'https://www.linkedin.com/feed/update/'.LINKEDIN_POST.'/'])
            ->and($request->header('Authorization')[0])->toBe('Bearer li_token')
            ->and($request->header('LinkedIn-Version')[0])->toBe(AbstractLinkedInNode::API_VERSION)
            ->and($request->header('X-Restli-Protocol-Version')[0])->toBe('2.0.0')
            ->and(linkedinBody('POST /rest/posts'))->toMatchArray(['author' => 'urn:li:person:abc123', 'commentary' => 'Launch day \(finally\) \#win', 'visibility' => 'PUBLIC', 'lifecycleState' => 'PUBLISHED']);
    });

    it('keeps markup as written when told it is markup', function () {
        fakeLinkedIn(['POST /rest/posts' => fn () => Http::response(null, 201, ['x-restli-id' => LINKEDIN_POST])]);

        (new LinkedInCreateTextPostNode)->execute(linkedinRun(), ['text' => '{hashtag|\#|win}', 'markup' => true], []);

        expect(linkedinBody('POST /rest/posts')['commentary'])->toBe('{hashtag|\#|win}');
    });

    it('posts as a company page, and refuses a connections-only page post or a bad author', function () {
        fakeLinkedIn(['POST /rest/posts' => fn () => Http::response(null, 201, ['x-restli-id' => LINKEDIN_POST])]);

        (new LinkedInCreateTextPostNode)->execute(linkedinRun(), ['text' => 'News', 'author' => '12345'], []);

        expect(linkedinBody('POST /rest/posts')['author'])->toBe('urn:li:organization:12345')
            ->and(fn () => (new LinkedInCreateTextPostNode)->execute(linkedinRun(), ['text' => 'x', 'author' => '12345', 'visibility' => 'CONNECTIONS'], []))->toThrow(InvalidArgumentException::class, 'publicly')
            ->and(fn () => (new LinkedInCreateTextPostNode)->execute(linkedinRun(), ['text' => 'x', 'author' => '../x'], []))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new LinkedInCreateTextPostNode)->execute(linkedinRun(), ['text' => ''], []))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new LinkedInCreateTextPostNode)->execute(linkedinRun(), ['text' => str_repeat('a', 3001)], []))->toThrow(InvalidArgumentException::class, '3000');
    });

    it('shares a link as an article and refuses a link that is not http', function () {
        fakeLinkedIn(['POST /rest/posts' => fn () => Http::response(null, 201, ['x-restli-id' => LINKEDIN_POST])]);

        (new LinkedInCreateLinkPostNode)->execute(linkedinRun(), ['url' => 'https://acme.com/blog', 'title' => 'The blog', 'text' => 'Read this'], []);

        expect(linkedinBody('POST /rest/posts')['content'])->toBe(['article' => ['source' => 'https://acme.com/blog', 'title' => 'The blog']])
            ->and(fn () => (new LinkedInCreateLinkPostNode)->execute(linkedinRun(), ['url' => 'javascript:alert(1)'], []))->toThrow(InvalidArgumentException::class);
    });

    it('downloads an image, uploads it and posts it', function () {
        fakeLinkedIn([
            'POST /rest/images' => ['value' => ['uploadUrl' => 'https://api.linkedin.com/upload/abc', 'image' => 'urn:li:image:C1']],
            'PUT /upload/abc' => fn () => Http::response(null, 201),
            'POST /rest/posts' => fn () => Http::response(null, 201, ['x-restli-id' => LINKEDIN_POST]),
        ]);
        $guard = Mockery::mock(GuardedHttp::class);
        $guard->shouldReceive('send')->once()->andReturn(new Illuminate\Http\Client\Response(new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'image/png'], 'PNGDATA')));
        app()->instance(GuardedHttp::class, $guard);

        $output = (new LinkedInCreateImagePostNode)->execute(linkedinRun(), ['image_url' => 'https://acme.com/launch.png', 'text' => 'Look', 'alt_text' => 'Launch'], []);

        expect($output['id'])->toBe(LINKEDIN_POST)
            ->and(linkedinBody('POST /rest/images'))->toBe(['initializeUploadRequest' => ['owner' => 'urn:li:person:abc123']])
            ->and(linkedinRequest('PUT /upload/abc')->body())->toBe('PNGDATA')
            ->and(linkedinBody('POST /rest/posts')['content'])->toBe(['media' => ['id' => 'urn:li:image:C1', 'altText' => 'Launch']]);
    });

    it('refuses an image that is not a picture', function () {
        fakeLinkedIn([]);
        $guard = Mockery::mock(GuardedHttp::class);
        $guard->shouldReceive('send')->once()->andReturn(new Illuminate\Http\Client\Response(new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'text/html'], '<html>')));
        app()->instance(GuardedHttp::class, $guard);

        expect(fn () => (new LinkedInCreateImagePostNode)->execute(linkedinRun(), ['image_url' => 'https://acme.com/page'], []))->toThrow(InvalidArgumentException::class, 'JPEG');
    });

    it('reads a post by its URN, and says a missing one is not found', function () {
        fakeLinkedIn(['GET /rest/posts/'.LINKEDIN_POST => linkedinPost()]);
        $found = (new LinkedInGetPostNode)->execute(linkedinRun(), ['post_id' => LINKEDIN_POST], []);

        fakeLinkedIn(['GET /rest/posts/'.LINKEDIN_POST => fn () => Http::response(['message' => 'gone'], 404)]);
        $missing = (new LinkedInGetPostNode)->execute(linkedinRun(), ['post_id' => LINKEDIN_POST], []);

        expect($found)->toMatchArray(['found' => true, 'text' => 'Hello', 'created_at' => gmdate('c', 1772000000)])
            ->and($missing)->toBe(['found' => false, 'id' => LINKEDIN_POST])
            ->and(fn () => (new LinkedInGetPostNode)->execute(linkedinRun(), ['post_id' => 'urn:li:share:1/../x'], []))->toThrow(InvalidArgumentException::class);
    });

    it('deletes a post, and says so when it is already gone', function () {
        fakeLinkedIn(['DELETE /rest/posts/'.LINKEDIN_POST => fn () => Http::response(null, 204)]);
        $gone = (new LinkedInDeletePostNode)->execute(linkedinRun(), ['post_id' => LINKEDIN_POST], []);

        fakeLinkedIn(['DELETE /rest/posts/'.LINKEDIN_POST => fn () => Http::response(['message' => 'x'], 404)]);

        expect($gone)->toBe(['deleted' => true, 'id' => LINKEDIN_POST])->and((new LinkedInDeletePostNode)->execute(linkedinRun(), ['post_id' => LINKEDIN_POST], []))->toBe(['deleted' => false, 'id' => LINKEDIN_POST]);
    });

    it('comments as the member and reacts with a checked reaction type', function () {
        fakeLinkedIn(['POST /rest/socialActions/'.LINKEDIN_POST.'/comments' => ['id' => '555'], 'POST /rest/reactions' => fn () => Http::response(null, 201)]);

        $comment = (new LinkedInCommentOnPostNode)->execute(linkedinRun(), ['post_id' => LINKEDIN_POST, 'text' => 'Nice (really)'], []);
        $like = (new LinkedInLikePostNode)->execute(linkedinRun(), ['post_id' => LINKEDIN_POST, 'reaction' => 'NOPE'], []);

        expect($comment)->toBe(['commented' => true, 'post' => LINKEDIN_POST, 'id' => '555'])
            ->and(linkedinBody('POST /rest/socialActions/'.LINKEDIN_POST.'/comments'))->toBe(['actor' => 'urn:li:person:abc123', 'message' => ['text' => 'Nice \(really\)']])
            ->and($like['reaction'])->toBe('LIKE')
            ->and(linkedinBody('POST /rest/reactions'))->toBe(['root' => LINKEDIN_POST, 'reactionType' => 'LIKE'])
            ->and(linkedinQuery('POST /rest/reactions'))->toMatchArray(['actor' => 'urn:li:person:abc123']);
    });

    it('lists the pages the member administers, naming each', function () {
        fakeLinkedIn(['GET /rest/organizationAcls' => ['elements' => [['organization' => 'urn:li:organization:12345'], ['organization' => 'bogus']]], 'GET /rest/organizations/12345' => ['localizedName' => 'Acme Inc', 'vanityName' => 'acme']]);

        $output = (new LinkedInListPagesNode)->execute(linkedinRun(), [], []);

        expect($output['pages'])->toBe([['id' => '12345', 'urn' => 'urn:li:organization:12345', 'name' => 'Acme Inc', 'vanity_name' => 'acme']]);
    });

    it('explains an error with a hint', function () {
        fakeLinkedIn(['GET /rest/posts/'.LINKEDIN_POST => fn () => Http::response(['status' => 401, 'message' => 'Invalid access token'], 401)]);

        expect(fn () => (new LinkedInGetPostNode)->execute(linkedinRun(), ['post_id' => LINKEDIN_POST], []))->toThrow(RuntimeException::class, 'Reconnect LinkedIn');
    });
});

describe('linkedin options', function () {
    it('lists the pages the member administers by id, filtered by the search', function () {
        fakeLinkedIn(['GET /rest/organizationAcls' => ['elements' => [['organization' => 'urn:li:organization:1'], ['organization' => 'urn:li:organization:2']]], 'GET /rest/organizations/1' => ['localizedName' => 'Acme Inc'], 'GET /rest/organizations/2' => ['localizedName' => 'Beta Labs']]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

        $page = app(LinkedInOptions::class)->load('linkedin.organizations', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'li_token', 'beta'));

        expect($page->toArray()['options'])->toBe([['value' => '2', 'label' => 'Beta Labs']]);
    });
});

describe('linkedin triggers', function () {
    it('polls new posts: a baseline first, then one run for each', function () {
        Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
        fakeLinkedIn([]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'linkedin') ?? Connector::factory()->create(['key' => 'linkedin']))
            ->create(['data' => ['api_key' => 'li_token'], 'created_by' => $owner->id]);
        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'linkedin_list_posts', 'config' => ['credential_id' => $credential->id, 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);
        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        SyncTriggerSubscription::dispatchSync($trigger->id);
        $poll = fn (array $ids) => fakeLinkedIn(['GET /rest/posts' => ['elements' => array_map(fn (string $id, int $n): array => linkedinPost($id, ['createdAt' => 1772000000000 + $n]), $ids, array_keys($ids))]]);

        $poll(['urn:li:share:1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['urn:li:share:3', 'urn:li:share:2', 'urn:li:share:1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['urn:li:share:2', 'urn:li:share:3']);
    });

    it('lists comments newest first for the new-comment trigger', function () {
        fakeLinkedIn(['GET /rest/socialActions/'.LINKEDIN_POST.'/comments' => ['elements' => [['id' => '1', 'actor' => 'urn:li:person:x', 'message' => ['text' => 'old'], 'created' => ['time' => 1772000000000]], ['id' => '2', 'actor' => 'urn:li:person:y', 'message' => ['text' => 'new'], 'created' => ['time' => 1772000100000]]]]]);

        $output = (new App\Nodes\Integrations\LinkedIn\LinkedInListCommentsNode)->execute(linkedinRun(), ['post_id' => LINKEDIN_POST], []);

        expect(array_column($output['comments'], 'id'))->toBe(['2', '1'])->and($output['comments'][0]['text'])->toBe('new');
    });
});
