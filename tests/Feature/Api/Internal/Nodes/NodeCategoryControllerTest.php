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

it('lists every node that can start a flow under Triggers & Events', function () {
    Passport::actingAs(User::factory()->create());

    $category = NodeCategory::factory()->create(['slug' => 'triggers-events']);

    $nodes = collect($this->getJson("/api/v1/node-categories/{$category->id}")->assertOk()->json('data.nodes'));

    expect($nodes->pluck('type'))->toContain('webhook_trigger', 'schedule_trigger', 'gmail_list_messages', 'slack_get_channel_history', 'google_sheets_get_values')
        ->and($nodes->firstWhere('type', 'gmail_list_messages'))->toMatchArray(['name' => 'Gmail: New email', 'category' => 'gmail', 'trigger_mode' => true])
        ->and($nodes->firstWhere('type', 'slack_post_message'))->toBeNull();
});

it('does not count the retired manual trigger in Triggers & Events', function () {
    Passport::actingAs(User::factory()->create());

    $category = NodeCategory::factory()->create(['slug' => 'triggers-events']);

    $response = $this->getJson("/api/v1/node-categories/{$category->id}")->assertOk();

    expect($response->json('data.nodes_count'))->toBe(collect($response->json('data.nodes'))->where('listed', true)->count())
        ->and(collect($response->json('data.nodes'))->firstWhere('type', 'manual_trigger')['listed'])->toBeFalse();
});

it('keeps the app readers listed under their own app too', function () {
    Passport::actingAs(User::factory()->create());

    $category = NodeCategory::factory()->create(['slug' => 'gmail']);

    $reader = collect($this->getJson("/api/v1/node-categories/{$category->id}")->assertOk()->json('data.nodes'))
        ->firstWhere('type', 'gmail_list_messages');

    expect($reader['name'])->toBe('Gmail: List Messages')
        ->and($reader)->not->toHaveKey('trigger_mode');
});
