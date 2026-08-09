<?php

use App\Models\Triggers\TriggerPreset;
use App\Models\User;
use Laravel\Passport\Passport;

it('lists active presets grouped by category', function () {
    TriggerPreset::factory()->github()->create();
    TriggerPreset::factory()->create(['category' => 'schedule', 'key' => 'schedule.daily']);
    TriggerPreset::factory()->create(['category' => 'github', 'key' => 'github.inactive', 'is_active' => false]);

    Passport::actingAs(User::factory()->create());

    $response = $this->getJson('/api/v1/catalog/trigger-presets');

    $response->assertOk();

    $presets = $response->json('data.presets');

    expect($presets)->toHaveKey('github');
    expect($presets)->toHaveKey('schedule');
    expect($presets['github'])->toHaveCount(1);
});
