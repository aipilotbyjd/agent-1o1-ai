<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Triggers\PollReader;
use App\Services\Triggers\TriggerService;
use App\Services\Workspaces\WorkspaceService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Answer one app. A key is "METHOD /path" under `$prefix` (a * takes any id); the
 * value is the JSON body (null is a 204), or a closure returning a response. A route
 * nobody answers is a 404, or `$fallback` when given, so a node that calls something
 * unexpected fails its test. A request to any other host is refused.
 *
 * @param  array<string, mixed>  $routes
 * @param  array<int|string, mixed>|null  $fallback
 */
function webDataFake(string $host, string $prefix, array $routes, ?array $fallback = null): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake([$host => function (Request $request) use ($prefix, $routes, $fallback) {
        $key = $request->method().' '.webDataPath($request, $prefix);
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return $fallback !== null ? Http::response($fallback) : Http::response(['message' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof PromiseInterface ? $answer : Http::response($answer ?? '', $answer === null ? 204 : 200);
    }]);
}

function webDataPath(Request $request, string $prefix): string
{
    return (string) preg_replace('~^'.preg_quote($prefix, '~').'~', '', rawurldecode((string) parse_url($request->url(), PHP_URL_PATH)));
}

/**
 * The one request made to a route.
 */
function webDataRequest(string $route, string $prefix): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.webDataPath($pair[0], $prefix) === $route));

    expect($found)->not->toBeEmpty();

    return end($found)[0];
}

/**
 * @return array<string, mixed>
 */
function webDataBody(string $route, string $prefix): array
{
    return (array) json_decode(webDataRequest($route, $prefix)->body(), true);
}

/**
 * A setting filled in for every field of a node, so the whole of its execute() runs.
 *
 * @param  array<string, mixed>  $schema
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function webDataConfigFor(array $schema, array $overrides = []): array
{
    $config = ['access_token' => 'the-key'];

    foreach ($schema['properties'] as $key => $field) {
        if (($field['x-hidden'] ?? false) || $key === 'credential_id') {
            continue;
        }

        $config[$key] = match (true) {
            str_contains($key, 'url') && ($field['type'] ?? '') === 'string' => 'https://example.com/a',
            isset($field['enum']) => $field['default'] ?? $field['enum'][0],
            ($field['x-widget'] ?? '') === 'list' => ['https://example.com/b'],
            ($field['x-widget'] ?? '') === 'emails' => 'ada@example.com',
            ($field['type'] ?? '') === 'integer' => $field['default'] ?? $field['minimum'] ?? 1,
            ($field['type'] ?? '') === 'boolean' => true,
            ($field['type'] ?? '') === 'array' => [['a' => 1]],
            ($field['type'] ?? '') === 'object' => ['a' => 1],
            default => 'abc123',
        };
    }

    return [...$config, ...$overrides];
}

/**
 * A published workflow starting from an app's reader node, as a trigger.
 *
 * @param  array<string, mixed>  $nodeConfig
 */
function webDataTrigger(string $connectorKey, string $nodeType, array $nodeConfig = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', $connectorKey) ?? Connector::factory()->create(['key' => $connectorKey]))
        ->create(['data' => ['api_key' => 'the-key'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();

    if ($sync) {
        SyncTriggerSubscription::dispatchSync($trigger->id);
    }

    return $trigger->fresh();
}

/**
 * One poll of a trigger, run here rather than through the faked queue.
 */
function webDataPoll(Trigger $trigger): void
{
    (new PollTrigger($trigger->fresh()))->handle(app(TriggerService::class), app(PollReader::class));
}

/**
 * The credential check's answer for a key, against a fake of the app.
 *
 * @return array{ok: bool, message: string, account: ?string, tested_at: string}
 */
function webDataCredentialCheck(string $connectorKey, string $key = 'the-key'): array
{
    $credential = ConnectorCredential::factory()
        ->forConnector(Connector::query()->firstWhere('key', $connectorKey) ?? Connector::factory()->create(['key' => $connectorKey]))
        ->create(['data' => ['api_key' => $key]]);

    return app(ConnectorCredentialTester::class)->test($credential);
}
