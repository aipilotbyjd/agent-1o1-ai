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
use App\Nodes\Integrations\Pinterest\AbstractPinterestNode;
use App\Nodes\Integrations\Pinterest\PinterestCreateBoardNode;
use App\Nodes\Integrations\Pinterest\PinterestCreatePinNode;
use App\Nodes\Integrations\Pinterest\PinterestDeleteBoardNode;
use App\Nodes\Integrations\Pinterest\PinterestDeletePinNode;
use App\Nodes\Integrations\Pinterest\PinterestGetPinNode;
use App\Nodes\Integrations\Pinterest\PinterestListBoardsNode;
use App\Nodes\Integrations\Pinterest\PinterestListPinsNode;
use App\Nodes\Integrations\Pinterest\PinterestPinAnalyticsNode;
use App\Nodes\Integrations\Pinterest\PinterestUpdateBoardNode;
use App\Nodes\Integrations\Pinterest\PinterestUpdatePinNode;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\PinterestOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const PINTEREST_PIN = '813744226420795884';
const PINTEREST_BOARD = '549755885175';

function pinterestRun(): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'pinterest') ?? Connector::factory()->create(['key' => 'pinterest']))->id,
        'data' => ['api_key' => 'pin_token'],
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Pinterest. A key is "METHOD /path" under /v5; the value is the JSON body, or
 * a closure returning a response. A route nobody answers is a 404.
 *
 * @param  array<string, mixed>  $routes
 */
function fakePinterest(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.pinterest.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.preg_replace('~^/v5~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['code' => 404, 'message' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : Http::response($answer);
    }]);
}

function pinterestRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/v5~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function pinterestBody(string $route): array
{
    return (array) json_decode(pinterestRequest($route)->body(), true);
}

/**
 * @return array<string, string>
 */
function pinterestQuery(string $route): array
{
    parse_str((string) parse_url(pinterestRequest($route)->url(), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function pinterestPin(string $id = PINTEREST_PIN, array $extra = []): array
{
    return ['id' => $id, 'title' => 'Pasta', 'description' => 'Easy', 'link' => 'https://acme.com/pasta', 'board_id' => PINTEREST_BOARD, 'created_at' => '2026-03-01T10:00:00', 'media' => ['media_type' => 'image', 'images' => ['600x' => ['url' => 'https://i.pinimg.com/600x/a.jpg']]], ...$extra];
}

describe('pinterest nodes', function () {
    it('registers the nodes under the pinterest connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'pinterest_'));

        expect(count($types))->toBeGreaterThanOrEqual(14);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractPinterestNode::class)->and($registry->resolve($type)->category())->toBe('pinterest');
        }
    });

    it('creates a pin from an image address with the token in a header', function () {
        fakePinterest(['POST /pins' => pinterestPin()]);

        $output = (new PinterestCreatePinNode)->execute(pinterestRun(), ['board_id' => PINTEREST_BOARD, 'image_url' => 'https://acme.com/pasta.jpg', 'title' => ' Pasta ', 'link' => 'https://acme.com/pasta', 'section_id' => '12345'], []);

        expect($output)->toMatchArray(['id' => PINTEREST_PIN, 'title' => 'Pasta', 'image_url' => 'https://i.pinimg.com/600x/a.jpg', 'url' => 'https://www.pinterest.com/pin/'.PINTEREST_PIN.'/'])
            ->and(pinterestRequest('POST /pins')->header('Authorization')[0])->toBe('Bearer pin_token')
            ->and(pinterestBody('POST /pins'))->toBe(['board_id' => PINTEREST_BOARD, 'board_section_id' => '12345', 'title' => 'Pasta', 'link' => 'https://acme.com/pasta', 'media_source' => ['source_type' => 'image_url', 'url' => 'https://acme.com/pasta.jpg']]);
    });

    it('refuses a pin with a bad image, link, board or too long a title', function () {
        fakePinterest([]);
        $run = pinterestRun();

        expect(fn () => (new PinterestCreatePinNode)->execute($run, ['board_id' => PINTEREST_BOARD, 'image_url' => 'ftp://x/y.jpg'], []))->toThrow(InvalidArgumentException::class, 'http')
            ->and(fn () => (new PinterestCreatePinNode)->execute($run, ['board_id' => PINTEREST_BOARD, 'image_url' => 'https://a.com/b.jpg', 'link' => 'javascript:x'], []))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new PinterestCreatePinNode)->execute($run, ['board_id' => '../x', 'image_url' => 'https://a.com/b.jpg'], []))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new PinterestCreatePinNode)->execute($run, ['board_id' => PINTEREST_BOARD, 'image_url' => 'https://a.com/b.jpg', 'title' => str_repeat('a', 101)], []))->toThrow(InvalidArgumentException::class, '100');
    });

    it('says a missing pin is not found, and shows one that is', function () {
        fakePinterest(['GET /pins/'.PINTEREST_PIN => fn () => Http::response(['code' => 404, 'message' => 'Pin not found'], 404)]);
        $missing = (new PinterestGetPinNode)->execute(pinterestRun(), ['pin_id' => PINTEREST_PIN], []);

        fakePinterest(['GET /pins/'.PINTEREST_PIN => pinterestPin()]);
        $found = (new PinterestGetPinNode)->execute(pinterestRun(), ['pin_id' => PINTEREST_PIN], []);

        expect($missing)->toBe(['found' => false, 'id' => PINTEREST_PIN])->and($found)->toMatchArray(['found' => true, 'board_id' => PINTEREST_BOARD]);
    });

    it('updates only what is filled in, and needs something to change', function () {
        fakePinterest(['PATCH /pins/'.PINTEREST_PIN => pinterestPin(extra: ['title' => 'New'])]);

        (new PinterestUpdatePinNode)->execute(pinterestRun(), ['pin_id' => PINTEREST_PIN, 'title' => 'New'], []);

        expect(pinterestBody('PATCH /pins/'.PINTEREST_PIN))->toBe(['title' => 'New'])
            ->and(fn () => (new PinterestUpdatePinNode)->execute(pinterestRun(), ['pin_id' => PINTEREST_PIN], []))->toThrow(InvalidArgumentException::class);
    });

    it('creates and updates a board, with a checked privacy', function () {
        fakePinterest(['POST /boards' => ['id' => PINTEREST_BOARD, 'name' => 'Dinners', 'privacy' => 'SECRET'], 'PATCH /boards/'.PINTEREST_BOARD => ['id' => PINTEREST_BOARD, 'name' => 'Dinners 2']]);

        $created = (new PinterestCreateBoardNode)->execute(pinterestRun(), ['name' => 'Dinners', 'privacy' => 'SECRET'], []);
        (new PinterestUpdateBoardNode)->execute(pinterestRun(), ['board_id' => PINTEREST_BOARD, 'name' => 'Dinners 2', 'privacy' => 'WRONG'], []);

        expect($created['privacy'])->toBe('SECRET')
            ->and(pinterestBody('POST /boards'))->toBe(['name' => 'Dinners', 'privacy' => 'SECRET'])
            ->and(pinterestBody('PATCH /boards/'.PINTEREST_BOARD))->toBe(['name' => 'Dinners 2']);
    });

    it('deletes a pin and a board, and says so when they are already gone', function () {
        fakePinterest(['DELETE /pins/'.PINTEREST_PIN => fn () => Http::response(null, 204), 'DELETE /boards/'.PINTEREST_BOARD => fn () => Http::response(['code' => 404, 'message' => 'x'], 404)]);

        expect((new PinterestDeletePinNode)->execute(pinterestRun(), ['pin_id' => PINTEREST_PIN], []))->toBe(['deleted' => true, 'id' => PINTEREST_PIN])
            ->and((new PinterestDeleteBoardNode)->execute(pinterestRun(), ['board_id' => PINTEREST_BOARD], []))->toBe(['deleted' => false, 'id' => PINTEREST_BOARD]);
    });

    it('lists pins following the bookmark, newest first', function () {
        $page = 0;
        fakePinterest(['GET /boards/'.PINTEREST_BOARD.'/pins' => function () use (&$page) {
            $page++;

            return $page === 1
                ? ['items' => array_map(fn (int $n): array => pinterestPin((string) (100000 + $n), ['created_at' => '2026-03-01T10:00:'.sprintf('%02d', $n % 60)]), range(1, 250)), 'bookmark' => 'next']
                : ['items' => [pinterestPin('999999', ['created_at' => '2026-03-02T10:00:00'])], 'bookmark' => null];
        }]);

        $output = (new PinterestListPinsNode)->execute(pinterestRun(), ['board_id' => PINTEREST_BOARD, 'limit' => 300], []);

        expect($output['count'])->toBe(251)->and($output['pins'][0]['id'])->toBe('999999')->and($page)->toBe(2);
    });

    it('reads pin analytics over the last days', function () {
        fakePinterest(['GET /pins/'.PINTEREST_PIN.'/analytics' => ['all' => ['summary_metrics' => ['IMPRESSION' => 120, 'SAVE' => 4]]]]);

        $output = (new PinterestPinAnalyticsNode)->execute(pinterestRun(), ['pin_id' => PINTEREST_PIN, 'days' => 7], []);

        expect($output['metrics'])->toBe(['IMPRESSION' => 120, 'SAVE' => 4])
            ->and(pinterestQuery('GET /pins/'.PINTEREST_PIN.'/analytics'))->toMatchArray(['metric_types' => 'IMPRESSION,SAVE,PIN_CLICK,OUTBOUND_CLICK']);
    });

    it('explains an error with a hint', function () {
        fakePinterest(['GET /pins/'.PINTEREST_PIN => fn () => Http::response(['code' => 2, 'message' => 'Authentication failed.'], 401)]);

        expect(fn () => (new PinterestGetPinNode)->execute(pinterestRun(), ['pin_id' => PINTEREST_PIN], []))->toThrow(RuntimeException::class, 'Reconnect Pinterest');
    });
});

describe('pinterest options', function () {
    it('lists boards, and the sections of the chosen board', function () {
        fakePinterest(['GET /boards' => ['items' => [['id' => PINTEREST_BOARD, 'name' => 'Dinners', 'privacy' => 'PUBLIC'], ['id' => '2', 'name' => 'Travel', 'privacy' => 'SECRET']], 'bookmark' => 'abc'], 'GET /boards/'.PINTEREST_BOARD.'/sections' => ['items' => [['id' => '77', 'name' => 'Desserts']]]]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $source = app(PinterestOptions::class);

        $boards = $source->load('pinterest.boards', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'pin_token', 'din'));
        $sections = $source->load('pinterest.sections', new NodeOptionsQuery($workspace, User::factory()->create(), ['board_id' => PINTEREST_BOARD], 'pin_token'));
        $none = $source->load('pinterest.sections', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'pin_token'));

        expect($boards->toArray()['options'])->toBe([['value' => PINTEREST_BOARD, 'label' => 'Dinners', 'description' => 'PUBLIC']])
            ->and($boards->toArray()['next_cursor'])->toBe('abc')
            ->and($sections->toArray()['options'])->toBe([['value' => '77', 'label' => 'Desserts']])
            ->and($none->toArray()['options'])->toBe([]);
    });
});

describe('pinterest triggers', function () {
    it('polls new pins: a baseline first, then one run for each', function () {
        Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
        fakePinterest([]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'pinterest') ?? Connector::factory()->create(['key' => 'pinterest']))
            ->create(['data' => ['api_key' => 'pin_token'], 'created_by' => $owner->id]);
        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'pinterest_list_pins', 'config' => ['credential_id' => $credential->id, 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);
        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        SyncTriggerSubscription::dispatchSync($trigger->id);
        $poll = fn (array $ids) => fakePinterest(['GET /pins' => ['items' => array_map(fn (string $id): array => pinterestPin($id), $ids)]]);

        $poll(['1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['3', '2', '1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['2', '3']);
    });

    it('lists boards for the new-board trigger', function () {
        fakePinterest(['GET /boards' => ['items' => [['id' => '1', 'name' => 'A', 'owner' => ['username' => 'ada']]]]]);

        $node = new PinterestListBoardsNode;
        $output = $node->execute(pinterestRun(), [], []);

        expect($output['boards'][0])->toMatchArray(['id' => '1', 'owner' => 'ada'])->and($node->triggerItemId($output['boards'][0]))->toBe('1');
    });
});
