<?php

use App\Actions\Agents\CreateAgentSessionAction;
use App\Actions\Agents\SendAgentMessageAction;
use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Agents\AgentMessageRole;
use App\Enums\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Prompts\AgentPrompt;

it('sends a message with no tools and completes a run', function () {
    WorkspaceAgent::fake(['Hello there!']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);
    $reply = app(SendAgentMessageAction::class)->execute($session, 'Hi!');

    expect($reply->role)->toBe(AgentMessageRole::Assistant);
    expect($reply->content)->toBe('Hello there!');
    expect($reply->usage)->not->toBeNull();

    expect($session->messages)->toHaveCount(2);
    expect($session->messages->first()->role)->toBe(AgentMessageRole::User);
    expect($session->messages->first()->content)->toBe('Hi!');

    $run = Run::where('runnable_type', 'agent_session')->where('runnable_id', $session->id)->sole();
    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->output['text'])->toBe('Hello there!');
    expect($run->output['message_id'])->toBe($reply->id);
});

it('excludes the just-sent user message from the prior-turn context', function () {
    WorkspaceAgent::fake(['reply one', 'reply two']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    app(SendAgentMessageAction::class)->execute($session, 'first message');
    app(SendAgentMessageAction::class)->execute($session, 'second message');

    // Two full turns persisted: user+assistant, user+assistant.
    expect($session->fresh()->messages)->toHaveCount(4);

    WorkspaceAgent::assertPrompted(function ($prompt) {
        return $prompt->prompt === 'second message';
    });
});

it('fails the run and rethrows when the provider call fails', function () {
    WorkspaceAgent::fake(function () {
        throw new RuntimeException('provider unavailable');
    });

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);

    expect(fn () => app(SendAgentMessageAction::class)->execute($session, 'hi'))
        ->toThrow(RuntimeException::class);

    $run = Run::where('runnable_type', 'agent_session')->where('runnable_id', $session->id)->sole();
    expect($run->status)->toBe(RunStatus::Failed);

    // The user's message is still recorded even though the reply failed.
    expect($session->fresh()->messages)->toHaveCount(1);
});

it('prompts using the resolved model catalog chain when the agent is opted in', function () {
    WorkspaceAgent::fake(['Hello there!']);

    $catalog = ModelCatalog::factory()->create(['slug' => 'claude-3-5-sonnet']);
    ModelRoute::factory()->forCatalog($catalog)->create([
        'execution_provider' => 'anthropic',
        'execution_model_id' => 'claude-3-5-sonnet-latest',
        'priority' => 0,
    ]);
    ModelRoute::factory()->forCatalog($catalog)->create([
        'execution_provider' => 'openrouter',
        'execution_model_id' => 'anthropic/claude-3.5-sonnet',
        'priority' => 1,
    ]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['model_catalog_id' => $catalog->id]);

    $session = app(CreateAgentSessionAction::class)->execute($agent, $owner);
    app(SendAgentMessageAction::class)->execute($session, 'Hi!');

    WorkspaceAgent::assertPrompted(function (AgentPrompt $prompt) {
        return $prompt->provider->name() === 'anthropic' && $prompt->model === 'claude-3-5-sonnet-latest';
    });
});
