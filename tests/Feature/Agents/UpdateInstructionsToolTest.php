<?php

use App\Ai\Tools\UpdateInstructionsTool;
use App\Models\Agents\Agent;
use App\Models\Agents\Skill;
use App\Models\User;
use App\Services\Agents\SkillInjector;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Tools\Request;

function selfUpdatingAgent(): Agent
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return Agent::factory()->forWorkspace($workspace)->create([
        'instructions' => 'You help customers.',
        'model' => 'live-model',
        'allow_self_updates' => true,
    ]);
}

it('rewrites the live agent\'s instructions as a new version', function () {
    $agent = selfUpdatingAgent();
    $versionsBefore = $agent->versions()->count();

    $pinnedCopy = (clone $agent)->forceFill(['model' => null, 'instructions' => 'An older pinned prompt.']);

    $result = (new UpdateInstructionsTool($pinnedCopy, app(SkillInjector::class)))->handle(new Request(['instructions' => 'You help customers. Always answer in Spanish.']));

    $agent->refresh();

    expect((string) $result)->toContain('Instructions updated');
    expect($agent->instructions)->toBe('You help customers. Always answer in Spanish.');
    expect($agent->model)->toBe('live-model');
    expect($agent->versions()->count())->toBe($versionsBefore + 1);
});

it('refuses to wipe the instructions', function () {
    $agent = selfUpdatingAgent();

    $result = (new UpdateInstructionsTool($agent, app(SkillInjector::class)))->handle(new Request(['instructions' => '   ']));

    expect((string) $result)->toContain('Not updated');
    expect($agent->fresh()->instructions)->toBe('You help customers.');
});

it('shows the model its live base instructions, not the pinned snapshot', function () {
    $agent = selfUpdatingAgent();
    $pinnedCopy = (clone $agent)->forceFill(['instructions' => 'An older pinned prompt.']);

    $description = (string) (new UpdateInstructionsTool($pinnedCopy, app(SkillInjector::class)))->description();

    expect($description)->toEndWith("Your current base instructions are:\n\nYou help customers.");
    expect($description)->not->toContain('An older pinned prompt.');
});

it('strips attached skills a model copies back into its instructions', function () {
    $agent = selfUpdatingAgent();
    $skill = Skill::factory()->create(['workspace_id' => $agent->workspace_id, 'name' => 'Tone', 'instructions' => 'Be concise.']);
    $agent->skills()->attach($skill->id);

    (new UpdateInstructionsTool($agent, app(SkillInjector::class)))->handle(new Request([
        'instructions' => "You help customers.\n\n## Skill: Tone\nBe concise.\n\nAlways answer in Spanish.",
    ]));

    expect($agent->fresh()->instructions)->toBe("You help customers.\n\nAlways answer in Spanish.");
});
