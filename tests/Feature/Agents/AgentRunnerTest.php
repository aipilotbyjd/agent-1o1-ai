<?php

use App\Actions\Agents\CreateAgentSessionAction;
use App\Actions\Agents\SendAgentMessageAction;
use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Agents\AgentMessageRole;
use App\Enums\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

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
