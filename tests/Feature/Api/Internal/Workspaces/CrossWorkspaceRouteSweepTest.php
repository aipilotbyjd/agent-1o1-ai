<?php

use App\Models\Agents\Agent;
use App\Models\Artifacts\Artifact;
use App\Models\Auth\ApiKey;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceInvitation;
use App\Services\Workspaces\WorkspaceService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

/*
 * Safety net for tenant isolation. For every route that takes exactly one
 * tenant-owned model — `/agents/{agent}`, `/secrets/{secret}`,
 * `/runs/{run}/cancel` and so on — this builds that model in a *different*
 * workspace, then requests it as the caller. A route that forgets its
 * ownership check serves (or mutates) the other tenant's row and fails here,
 * including routes added in future.
 *
 * Two surfaces: the internal API, where the caller is a member of a workspace
 * in the URL, and the public API, where an API key *is* the workspace.
 *
 * Routes with several bound models (`/agents/{agent}/sessions/{session}`) are
 * not swept: the foreign row can't be built without a matching parent. Their
 * checks are covered by the feature tests of each controller.
 */

/**
 * @return list<array{route: LaravelRoute, parameter: string, model: class-string<Model>}>
 */
function singleModelRoutes(string $uriPrefix): array
{
    $swept = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), $uriPrefix)) {
            continue;
        }

        $models = [];

        foreach ($route->signatureParameters(['subClass' => Model::class]) as $parameter) {
            if ($parameter->getName() !== 'workspace' && in_array($parameter->getName(), $route->parameterNames(), true)) {
                $models[$parameter->getName()] = $parameter->getType()->getName();
            }
        }

        $parametersInUri = array_diff($route->parameterNames(), ['workspace']);

        if (count($models) !== 1 || count($parametersInUri) !== 1) {
            continue;
        }

        $swept[] = ['route' => $route, 'parameter' => array_key_first($models), 'model' => reset($models)];
    }

    return $swept;
}

/**
 * A row of `$class` in `$workspace`, or null when the model is not tenant-owned
 * or cannot be built. Most models have a factory; these three do not.
 *
 * @param  class-string<Model>  $class
 */
function foreignRow(string $class, Workspace $workspace): ?Model
{
    if (! Schema::hasColumn((new $class)->getTable(), 'workspace_id')) {
        return null;
    }

    return match ($class) {
        Artifact::class => Artifact::query()->create([
            'workspace_id' => $workspace->id,
            'agent_id' => Agent::factory()->forWorkspace($workspace)->create()->id,
            'group_id' => (string) Str::uuid(),
            'filename' => 'report.txt',
            'mime_type' => 'text/plain',
            'size' => 1,
            'path' => 'artifacts/report.txt',
        ]),
        ApiKey::class => ApiKey::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Key',
            'hashed_key' => hash('sha256', Str::random(40)),
            'abilities' => [],
        ]),
        WorkspaceInvitation::class => WorkspaceInvitation::query()->create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
            'token' => Str::random(40),
            'invited_by' => $workspace->owner_id,
            'expires_at' => now()->addDay(),
        ]),
        default => method_exists($class, 'factory') ? $class::factory()->create(['workspace_id' => $workspace->id]) : null,
    };
}

/**
 * Requests every swept route with a row from `$theirs` and returns the ones
 * that did not refuse it, plus how many requests were made.
 *
 * @param  Closure(string, string): TestResponse  $send  (method, uri) → response
 * @param  Closure(LaravelRoute, string): string  $uriFor  (route, row key) → uri
 * @return array{leaks: list<string>, checked: int, unswept: list<class-string>}
 */
function sweep(string $uriPrefix, Workspace $theirs, Closure $send, Closure $uriFor): array
{
    $result = ['leaks' => [], 'checked' => 0, 'unswept' => []];

    foreach (singleModelRoutes($uriPrefix) as ['route' => $route, 'parameter' => $parameter, 'model' => $class]) {
        try {
            $foreign = foreignRow($class, $theirs);
        } catch (Throwable) {
            $foreign = null;
        }

        if ($foreign === null) {
            $result['unswept'][] = $class;

            continue;
        }

        $uri = str_replace('{'.$parameter.'}', (string) $foreign->getKey(), $uriFor($route, $parameter));

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $status = $send($method, $uri)->getStatusCode();
            $result['checked']++;

            if ($status < 400 || $status >= 500) {
                $result['leaks'][] = "{$method} {$route->uri()} → {$status}";
            }
        }
    }

    $result['unswept'] = array_values(array_unique($result['unswept']));

    return $result;
}

it('never serves or changes another workspaces row through a workspace route', function () {
    $owner = User::factory()->create();
    $mine = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $theirs = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Globex']);

    Passport::actingAs($owner);

    $result = sweep(
        'api/v1/workspaces/{workspace}/',
        $theirs,
        fn (string $method, string $uri) => $this->json($method, $uri),
        fn (LaravelRoute $route) => '/'.str_replace('{workspace}', $mine->id, $route->uri()),
    );

    // `User` is the only tenant-less model on these routes (`shares/{user}`).
    expect($result['leaks'])->toBe([])
        ->and($result['unswept'])->toBe([User::class])
        ->and($result['checked'])->toBeGreaterThan(100);
});

it('never serves or changes another workspaces row through the public api', function () {
    $mine = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $theirs = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Globex']);

    $key = ApiKey::generatePlainTextKey();
    $mine->apiKeys()->create(['name' => 'Everything', 'hashed_key' => ApiKey::hash($key), 'abilities' => ['*']]);

    $result = sweep(
        'api/public/v1/',
        $theirs,
        fn (string $method, string $uri) => $this->withToken($key)->json($method, $uri),
        fn (LaravelRoute $route) => '/'.$route->uri(),
    );

    expect($result['leaks'])->toBe([])
        ->and($result['unswept'])->toBe([])
        ->and($result['checked'])->toBeGreaterThan(15);
});
