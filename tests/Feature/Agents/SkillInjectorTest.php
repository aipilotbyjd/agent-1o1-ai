<?php

use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Agents\SkillInjector;
use App\Services\Workspaces\WorkspaceService;

it('returns just the base instructions when nothing is attached', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'Be helpful.']);

    expect(app(SkillInjector::class)->instructionsFor($agent))->toBe('Be helpful.');
});

it('appends attached skills and active knowledge, but skips inactive knowledge', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'Be helpful.']);

    $skill = $agent->workspace->skills()->create([
        'name' => 'Refund Policy',
        'slug' => 'refund-policy',
        'instructions' => 'Offer store credit before a cash refund.',
    ]);
    $agent->skills()->attach($skill->id);

    $agent->knowledge()->create(['title' => 'Hours', 'content' => 'We are open 9-5.', 'sort_order' => 1]);
    $agent->knowledge()->create(['title' => 'Hidden', 'content' => 'Should not appear.', 'is_active' => false]);

    $instructions = app(SkillInjector::class)->instructionsFor($agent);

    expect($instructions)->toContain('Be helpful.');
    expect($instructions)->toContain('## Skill: Refund Policy');
    expect($instructions)->toContain('Offer store credit before a cash refund.');
    expect($instructions)->toContain('## Knowledge: Hours');
    expect($instructions)->toContain('We are open 9-5.');
    expect($instructions)->not->toContain('Hidden');
    expect($instructions)->not->toContain('Should not appear.');
});
