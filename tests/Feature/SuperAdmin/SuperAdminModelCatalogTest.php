<?php

use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\Ai\ModelCatalogResolver;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();

    signInAsSuperAdmin(superAdmin());
});

/**
 * @return array<string, mixed>
 */
function catalogForm(array $overrides = []): array
{
    return [
        'display_name' => 'Claude Sonnet 5',
        'brand' => 'anthropic',
        'description' => 'Balanced model',
        'strengths' => "All-round\nCoding\n\nTool use",
        'speed' => 'balanced',
        'cost_tier' => 2,
        'context_window' => 200000,
        'vision' => '1',
        'tool_use' => '1',
        'is_active' => '1',
        'is_internal' => '0',
        'sort_order' => 3,
        ...$overrides,
    ];
}

it('lists the catalog and flags an active model with no enabled route', function () {
    $healthy = ModelCatalog::factory()->create(['display_name' => 'Healthy Model']);
    ModelRoute::factory()->forCatalog($healthy)->create();
    $broken = ModelCatalog::factory()->create(['display_name' => 'Broken Model']);
    ModelRoute::factory()->forCatalog($broken)->disabled()->create();

    $this->get(route('super-admin.models.index'))
        ->assertOk()
        ->assertSee('Healthy Model')
        ->assertSee('Broken Model')
        ->assertSee('No enabled route', false);
});

it('adds a model and logs it', function () {
    $this->post(route('super-admin.models.store'), catalogForm(['slug' => 'claude-sonnet-5']))->assertRedirect();

    $entry = ModelCatalog::query()->where('slug', 'claude-sonnet-5')->sole();

    expect($entry->strengths)->toBe(['All-round', 'Coding', 'Tool use'])
        ->and($entry->capabilities)->toMatchArray(['context_window' => 200000, 'vision' => true, 'tool_use' => true])
        ->and($entry->speed->value)->toBe('balanced')
        ->and(SuperAdminAuditLog::query()->where('action', 'model_catalog.created')->exists())->toBeTrue();
});

it('refuses a duplicate slug and never lets the slug change', function () {
    $entry = ModelCatalog::factory()->create(['slug' => 'taken-model']);

    $this->post(route('super-admin.models.store'), catalogForm(['slug' => 'taken-model']))->assertSessionHasErrors('slug');

    $this->put(route('super-admin.models.update', $entry), catalogForm(['slug' => 'renamed']))->assertSessionHasErrors('slug');
    expect($entry->fresh()->slug)->toBe('taken-model');
});

it('keeps capabilities it does not edit', function () {
    $entry = ModelCatalog::factory()->create(['capabilities' => ['context_window' => 1000, 'json_mode' => true]]);

    $this->put(route('super-admin.models.update', $entry), catalogForm(['context_window' => 5000, 'vision' => '0']))->assertRedirect();

    expect($entry->fresh()->capabilities)->toMatchArray(['context_window' => 5000, 'vision' => false, 'json_mode' => true]);
});

it('adds, re-orders and removes routes, dropping the cached chain every time', function () {
    $entry = ModelCatalog::factory()->create(['slug' => 'routed-model']);
    $first = ModelRoute::factory()->forCatalog($entry)->create(['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-x', 'priority' => 0]);

    $resolver = app(ModelCatalogResolver::class);
    expect($resolver->providerChain('routed-model'))->toBe(['anthropic' => 'claude-x']);

    $this->post(route('super-admin.models.routes.store', $entry), [
        'execution_provider' => 'openrouter',
        'execution_model_id' => 'anthropic/claude-x',
        'priority' => 1,
        'is_enabled' => '1',
    ])->assertRedirect();

    expect($resolver->providerChain('routed-model'))->toBe(['anthropic' => 'claude-x', 'openrouter' => 'anthropic/claude-x']);

    $this->put(route('super-admin.models.routes.update', [$entry, $first]), ['priority' => 5, 'is_enabled' => '0'])->assertRedirect();
    expect($resolver->providerChain('routed-model'))->toBe(['openrouter' => 'anthropic/claude-x']);

    $this->delete(route('super-admin.models.routes.destroy', [$entry, $first]))->assertRedirect();
    expect($entry->routes()->count())->toBe(1)
        ->and(SuperAdminAuditLog::query()->where('action', 'like', 'model_route.%')->count())->toBe(3);
});

it('only accepts providers defined in the AI config', function () {
    $entry = ModelCatalog::factory()->create();

    $this->post(route('super-admin.models.routes.store', $entry), [
        'execution_provider' => 'evil-provider',
        'execution_model_id' => 'x',
        'priority' => 0,
        'is_enabled' => '1',
    ])->assertSessionHasErrorsIn('route', 'execution_provider');
});

it('will not touch a route through another model', function () {
    $entry = ModelCatalog::factory()->create();
    $otherRoute = ModelRoute::factory()->forCatalog(ModelCatalog::factory()->create())->create(['priority' => 0]);

    $this->put(route('super-admin.models.routes.update', [$entry, $otherRoute]), ['priority' => 9, 'is_enabled' => '1'])->assertNotFound();
    $this->delete(route('super-admin.models.routes.destroy', [$entry, $otherRoute]))->assertNotFound();

    expect($otherRoute->fresh()->priority)->toBe(0);
});

it('resets a route failure count', function () {
    $entry = ModelCatalog::factory()->create();
    $route = ModelRoute::factory()->forCatalog($entry)->create(['failure_count' => 7, 'last_failed_at' => now()]);

    $this->post(route('super-admin.models.routes.reset-failures', [$entry, $route]))->assertRedirect();

    expect($route->fresh()->failure_count)->toBe(0)
        ->and($route->fresh()->last_failed_at)->toBeNull();
});

it('renders the model pages', function () {
    $entry = ModelCatalog::factory()->create();
    ModelRoute::factory()->forCatalog($entry)->create();

    $this->get(route('super-admin.models.show', $entry))->assertOk()->assertSee('Add a route');
    $this->get(route('super-admin.models.edit', $entry))->assertOk();
    $this->get(route('super-admin.models.create'))->assertOk();
});
