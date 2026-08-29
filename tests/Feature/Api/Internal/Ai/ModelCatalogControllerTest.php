<?php

use App\Models\Ai\ModelCatalog;
use App\Models\User;
use Laravel\Passport\Passport;

it('lists active model catalog entries without exposing execution routes', function () {
    $user = User::factory()->create();
    Passport::actingAs($user);

    ModelCatalog::factory()->create([
        'slug' => 'claude-3-5-sonnet',
        'display_name' => 'Claude 3.5 Sonnet',
        'brand' => 'anthropic',
        'capabilities' => ['context_window' => 200000],
    ]);
    ModelCatalog::factory()->create(['is_active' => false]);
    ModelCatalog::factory()->create(['slug' => 'workflow-builder-assistant', 'is_internal' => true]);

    $response = $this->getJson('/api/v1/model-catalog');

    $response->assertOk();
    $response->assertJsonCount(1, 'data.model_catalog');
    $response->assertJsonFragment([
        'slug' => 'claude-3-5-sonnet',
        'display_name' => 'Claude 3.5 Sonnet',
        'brand' => 'anthropic',
    ]);
    $response->assertJsonMissingPath('data.model_catalog.0.routes');
    $response->assertJsonMissingPath('data.model_catalog.0.execution_provider');
});

it('requires authentication', function () {
    $this->getJson('/api/v1/model-catalog')->assertUnauthorized();
});
