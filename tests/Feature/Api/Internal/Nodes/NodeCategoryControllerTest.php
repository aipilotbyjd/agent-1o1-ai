<?php

use App\Models\Nodes\NodeCategory;
use App\Models\User;
use Laravel\Passport\Passport;

it('lists node categories ordered by sort_order', function () {
    Passport::actingAs(User::factory()->create());

    NodeCategory::factory()->create(['name' => 'Second', 'slug' => 'second', 'sort_order' => 2]);
    NodeCategory::factory()->create(['name' => 'First', 'slug' => 'first', 'sort_order' => 1]);

    $response = $this->getJson('/api/v1/node-categories');

    $response->assertOk();
    expect($response->json('data.categories.*.slug'))->toBe(['first', 'second']);
});

it('requires authentication to list node categories', function () {
    $this->getJson('/api/v1/node-categories')->assertUnauthorized();
});

it('shows a single category with its builtin nodes', function () {
    Passport::actingAs(User::factory()->create());

    $category = NodeCategory::factory()->create(['slug' => 'slack']);

    $response = $this->getJson("/api/v1/node-categories/{$category->id}");

    $response->assertOk();
    expect($response->json('data.category.slug'))->toBe('slack');
    expect($response->json('data.nodes_count'))->toBe(7);
    expect(collect($response->json('data.nodes'))->pluck('type'))->toContain('slack_post_message');
});
