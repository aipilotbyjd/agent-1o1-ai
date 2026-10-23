<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Ai\AiEndpoint;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\Ai\ModelCatalogResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->operator = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->operator);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function endpointPayload(array $overrides = []): array
{
    return [
        'slug' => 'my-vllm',
        'name' => 'Our vLLM box',
        'base_url' => 'https://93.184.216.34/v1',
        'api_key' => 'sk-secret-key-1234',
        ...$overrides,
    ];
}

it('adds an endpoint, and it works as a provider at once', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('models/endpoints'), stepUp($this->operator, endpointPayload()))
        ->assertCreated()
        ->assertJsonPath('data.slug', 'my-vllm')
        ->assertJsonPath('data.has_key', true)
        ->assertJsonPath('data.key_hint', '…1234')
        ->assertJsonPath('data.is_enabled', true)
        ->assertJsonMissingPath('data.api_key');

    expect(ModelCatalogResolver::providerIsConfigured('my-vllm'))->toBeTrue()
        ->and(config('ai.providers.my-vllm.key'))->toBe('sk-secret-key-1234');
});

it('keeps the key out of the audit log, recording only that there is one', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('models/endpoints'), stepUp($this->operator, endpointPayload()))->assertCreated();

    $log = SuperAdminAuditLog::query()->where('action', 'models.endpoint.created')->firstOrFail();

    expect(json_encode($log->toArray()))->not->toContain('sk-secret-key-1234')
        ->and($log->after)->toMatchArray(['slug' => 'my-vllm', 'base_url' => 'https://93.184.216.34/v1', 'has_key' => true]);
});

it('takes an endpoint that needs no key', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('models/endpoints'), stepUp($this->operator, endpointPayload(['api_key' => null])))
        ->assertCreated()->assertJsonPath('data.has_key', false)->assertJsonPath('data.key_hint', null);

    expect(ModelCatalogResolver::providerIsConfigured('my-vllm'))->toBeTrue();
});

it('needs a reason and a fresh authenticator code', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('models/endpoints'), endpointPayload())
        ->assertUnprocessable()->assertJsonValidationErrors(['reason', 'totp_code']);

    $this->withHeaders($this->headers)->postJson(saUrl('models/endpoints'), [...stepUp($this->operator, endpointPayload()), 'totp_code' => '000000'])
        ->assertUnprocessable()->assertJsonValidationErrors('totp_code');

    expect(AiEndpoint::query()->count())->toBe(0);
});

it('validates an endpoint', function (array $input, string $error) {
    AiEndpoint::factory()->create(['slug' => 'taken-one']);

    $this->withHeaders($this->headers)->postJson(saUrl('models/endpoints'), stepUp($this->operator, endpointPayload($input)))
        ->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'a name already used' => [['slug' => 'taken-one'], 'slug'],
    'the name of a real provider' => [['slug' => 'openai'], 'slug'],
    'the name of another real provider' => [['slug' => 'fireworks'], 'slug'],
    'a name that looks like a workspace key' => [['slug' => 'byok-abc'], 'slug'],
    'capitals' => [['slug' => 'My_VLLM'], 'slug'],
    'too short' => [['slug' => 'ab'], 'slug'],
    'no name' => [['name' => ''], 'name'],
    'no URL' => [['base_url' => ''], 'base_url'],
    'a private address' => [['base_url' => 'https://10.0.0.5/v1'], 'base_url'],
    'the cloud metadata address' => [['base_url' => 'https://169.254.169.254/latest'], 'base_url'],
    'a key inside the URL' => [['base_url' => 'https://user:pass@93.184.216.34/v1'], 'base_url'],
    'a file URL' => [['base_url' => 'file:///etc/passwd'], 'base_url'],
]);

it('allows a private host the deployment has named', function () {
    config(['super_admin_security.endpoints.private_hosts' => ['10.0.0.5']]);

    $this->withHeaders($this->headers)->postJson(saUrl('models/endpoints'), stepUp($this->operator, endpointPayload(['base_url' => 'https://10.0.0.5/v1'])))
        ->assertCreated();
});

it('lists endpoints, to operators and owners only', function (SuperAdminRole $role, int $status) {
    AiEndpoint::factory()->create(['slug' => 'my-vllm']);

    $this->withHeaders(superAdminHeaders(superAdmin($role)))->getJson(saUrl('models/endpoints'))->assertStatus($status);
})->with([
    'owner' => [SuperAdminRole::Owner, 200],
    'operator' => [SuperAdminRole::Operator, 200],
    'finance' => [SuperAdminRole::Finance, 403],
    'viewer' => [SuperAdminRole::Viewer, 403],
]);

it('replaces the key, clears it, or leaves it alone', function () {
    $endpoint = AiEndpoint::factory()->create(['slug' => 'my-vllm', 'api_key' => 'old-key-0001']);
    $url = saUrl("models/endpoints/{$endpoint->id}");

    $this->withHeaders($this->headers)->patchJson($url, stepUp($this->operator, ['name' => 'Renamed']))
        ->assertOk()->assertJsonPath('data.key_hint', '…0001');
    expect($endpoint->fresh()->api_key)->toBe('old-key-0001');

    $this->withHeaders($this->headers)->patchJson($url, stepUp($this->operator, ['api_key' => 'new-key-0002']))
        ->assertOk()->assertJsonPath('data.key_hint', '…0002');
    expect($endpoint->fresh()->api_key)->toBe('new-key-0002');

    $this->withHeaders($this->headers)->patchJson($url, stepUp($this->operator, ['api_key' => '']))->assertOk();
    expect($endpoint->fresh()->api_key)->toBe('new-key-0002');

    $this->withHeaders($this->headers)->patchJson($url, stepUp($this->operator, ['clear_api_key' => true]))
        ->assertOk()->assertJsonPath('data.has_key', false);
    expect($endpoint->fresh()->api_key)->toBeNull();

    $logs = SuperAdminAuditLog::query()->where('action', 'models.endpoint.updated')->get();

    expect($logs)->toHaveCount(4)
        ->and(json_encode($logs->toArray()))->not->toContain('old-key-0001')->not->toContain('new-key-0002')
        ->and($logs->pluck('after.key_changed')->all())->toContain(true, false);
});

it('switches an endpoint off and a worker stops using it', function () {
    $endpoint = AiEndpoint::factory()->create(['slug' => 'my-vllm']);
    expect(ModelCatalogResolver::providerIsConfigured('my-vllm'))->toBeTrue();

    $this->withHeaders($this->headers)->patchJson(saUrl("models/endpoints/{$endpoint->id}"), stepUp($this->operator, ['is_enabled' => false]))->assertOk();

    expect(ModelCatalogResolver::providerIsConfigured('my-vllm'))->toBeFalse();
});

it('checks the address again when it is changed', function () {
    $endpoint = AiEndpoint::factory()->create();

    $this->withHeaders($this->headers)->patchJson(saUrl("models/endpoints/{$endpoint->id}"), stepUp($this->operator, ['base_url' => 'https://127.0.0.1/v1']))
        ->assertUnprocessable()->assertJsonValidationErrors('base_url');
});

it('refuses to delete an endpoint a route still uses, and deletes one nothing uses', function () {
    $endpoint = AiEndpoint::factory()->create(['slug' => 'my-vllm']);
    $model = ModelCatalog::factory()->create(['slug' => 'house']);
    $route = ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'my-vllm', 'execution_model_id' => 'llama']);

    $this->withHeaders($this->headers)->deleteJson(saUrl("models/endpoints/{$endpoint->id}"), stepUp($this->operator))->assertStatus(409);
    expect(AiEndpoint::query()->count())->toBe(1);

    $route->delete();

    $this->withHeaders($this->headers)->deleteJson(saUrl("models/endpoints/{$endpoint->id}"), stepUp($this->operator))->assertNoContent();

    expect(AiEndpoint::query()->count())->toBe(0)
        ->and(SuperAdminAuditLog::query()->where('action', 'models.endpoint.deleted')->exists())->toBeTrue();
});

it('tests an endpoint by asking for its model list, and keeps the answer', function () {
    Http::fake(['93.184.216.34/v1/models' => Http::response(['data' => []])]);
    $endpoint = AiEndpoint::factory()->create(['api_key' => 'k-1234']);

    $this->withHeaders($this->headers)->postJson(saUrl("models/endpoints/{$endpoint->id}/test"))
        ->assertOk()->assertJsonPath('data.ok', true);

    Http::assertSent(fn ($request) => $request->url() === 'https://93.184.216.34/v1/models' && $request->hasHeader('Authorization', 'Bearer k-1234'));

    expect($endpoint->fresh())->last_check_ok->toBeTrue()->last_checked_at->not->toBeNull();
});

it('records an endpoint that answers with an error or does not answer', function () {
    $endpoint = AiEndpoint::factory()->create();

    Http::fake(['*' => Http::response('nope', 401)]);
    $this->withHeaders($this->headers)->postJson(saUrl("models/endpoints/{$endpoint->id}/test"))
        ->assertOk()->assertJsonPath('data.ok', false)->assertJsonPath('data.message', 'The endpoint answered HTTP 401.');

    Http::fake(['*' => fn () => throw new ConnectionException('timed out')]);
    $this->withHeaders($this->headers)->postJson(saUrl("models/endpoints/{$endpoint->id}/test"))
        ->assertOk()->assertJsonPath('data.ok', false);

    expect($endpoint->fresh()->last_check_message)->toContain('Could not connect');
});

it('does not call an address the guard has since refused', function () {
    Http::fake();
    $endpoint = AiEndpoint::factory()->create(['base_url' => 'https://10.0.0.5/v1']);

    $this->withHeaders($this->headers)->postJson(saUrl("models/endpoints/{$endpoint->id}/test"))
        ->assertOk()->assertJsonPath('data.ok', false);

    Http::assertNothingSent();
});
