<?php

use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Agents\SkillInjector;
use App\Services\Workspaces\WorkspaceService;

it('puts who the agent is ahead of its base instructions', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create([
        'name' => 'Competitor Scout',
        'description' => 'Researches competitors.',
        'instructions' => 'Be helpful.',
    ]);

    $instructions = app(SkillInjector::class)->instructionsFor($agent);

    expect($instructions)->toStartWith('# About you')
        ->toContain('You are "Competitor Scout"')
        ->toContain('Your purpose: Researches competitors.')
        ->toContain('Today is '.now()->format('l, F j, Y'))
        ->toContain('You cannot change your own instructions')
        ->toEndWith('Be helpful.');
});

it('tells a self-updating agent it may rewrite its instructions', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['allow_self_updates' => true]);

    expect(app(SkillInjector::class)->instructionsFor($agent))
        ->toContain('update your own instructions')
        ->not->toContain('You cannot change your own instructions');
});

it('lists attached skills without their instructions, and appends active knowledge but not inactive', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'Be helpful.']);

    $skill = $agent->workspace->skills()->create([
        'name' => 'Refund Policy',
        'slug' => 'refund-policy',
        'description' => 'How to handle refund requests.',
        'instructions' => 'Offer store credit before a cash refund.',
    ]);
    $agent->skills()->attach($skill->id);

    $agent->knowledge()->create(['title' => 'Hours', 'content' => 'We are open 9-5.', 'sort_order' => 1]);
    $agent->knowledge()->create(['title' => 'Hidden', 'content' => 'Should not appear.', 'is_active' => false]);

    $instructions = app(SkillInjector::class)->instructionsFor($agent);

    expect($instructions)->toContain('Be helpful.');
    expect($instructions)->toContain('## Skills');
    expect($instructions)->toContain('call `use_skill`');
    expect($instructions)->toContain('- Refund Policy: How to handle refund requests.');
    expect($instructions)->not->toContain('Offer store credit before a cash refund.');
    expect($instructions)->toContain('## Knowledge: Hours');
    expect($instructions)->toContain('We are open 9-5.');
    expect($instructions)->not->toContain('Hidden');
    expect($instructions)->not->toContain('Should not appear.');
});

it('appends workspace-wide memories plus the given user\'s own, but not another user\'s', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'Be helpful.']);

    $agent->memories()->create(['key' => 'company_name', 'value' => 'Acme Inc']);
    $agent->memories()->create(['key' => 'favorite_color', 'value' => 'blue', 'user_id' => $owner->id]);
    $agent->memories()->create(['key' => 'favorite_color', 'value' => 'green', 'user_id' => $other->id]);

    $instructions = app(SkillInjector::class)->instructionsFor($agent, $owner->id);

    expect($instructions)->toContain('## Things you remember');
    expect($instructions)->toContain('- company_name: Acme Inc');
    expect($instructions)->toContain('- favorite_color: blue');
    expect($instructions)->not->toContain('- favorite_color: green');
});

it('omits the memories section entirely when there are none in scope', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'Be helpful.']);

    expect(app(SkillInjector::class)->instructionsFor($agent, $owner->id))->not->toContain('## Things you remember');
});

it('leaves out empty base instructions', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => null]);

    expect(app(SkillInjector::class)->instructionsFor($agent))->toStartWith('# About you')->not->toContain("\n\n\n");
});
