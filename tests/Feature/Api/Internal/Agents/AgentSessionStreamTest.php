<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Agents\AgentMessageRole;
use App\Enums\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Passport\Passport;

it('streams a turn as server-sent events and persists the reply', function () {
    WorkspaceAgent::fake(['Streaming hello!']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id]);

    Passport::actingAs($owner);

    $response = $this->post(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions/{$session->id}/messages/stream",
        ['message' => 'Hi!'],
        ['Accept' => 'text/event-stream'],
    );

    $response->assertOk();
    $body = $response->streamedContent();

    expect($body)->toContain('event: delta');
    expect($body)->toContain('event: complete');
    expect($body)->toContain('event: done');

    $reply = $session->messages()->where('role', AgentMessageRole::Assistant)->sole();
    expect($reply->content)->toBe('Streaming hello!');

    $run = Run::where('runnable_id', $session->id)->where('runnable_type', 'agent_session')->sole();
    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->output['message_id'])->toBe($reply->id);
});

it('streams each tool\'s output and keeps it on the stored reply for the timeline', function () {
    WorkspaceAgent::fake([
        new ToolCall('call-1', 'remember', ['key' => 'preferred_name', 'value' => 'JD']),
        'Got it, JD!',
    ]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id]);

    Passport::actingAs($owner);

    $body = $this->post(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions/{$session->id}/messages/stream",
        ['message' => 'Call me JD.'],
        ['Accept' => 'text/event-stream'],
    )->streamedContent();

    expect($body)->toContain('event: tool-result')
        ->toContain('"output":"Remembered preferred_name."')
        ->toContain('"successful":true');

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions/{$session->id}")
        ->assertOk()
        ->assertJsonPath('data.session.messages.1.tool_results.0', [
            'id' => 'call-1',
            'name' => 'remember',
            'output' => 'Remembered preferred_name.',
        ]);
});

it('marks the turn failed and emits an error event when the provider blows up', function () {
    WorkspaceAgent::fake(function () {
        throw new RuntimeException('provider unavailable');
    });

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id]);

    Passport::actingAs($owner);

    $body = $this->post(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions/{$session->id}/messages/stream",
        ['message' => 'Hi!'],
    )->streamedContent();

    expect($body)->toContain('event: error');

    $run = Run::where('runnable_id', $session->id)->where('runnable_type', 'agent_session')->sole();
    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->error)->toContain('provider unavailable');
});

it('404s streaming into a session that belongs to another agent', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $other = Agent::factory()->forWorkspace($workspace)->create();
    $session = $other->sessions()->create(['workspace_id' => $workspace->id]);

    Passport::actingAs($owner);

    $this->post(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions/{$session->id}/messages/stream",
        ['message' => 'Hi!'],
    )->assertNotFound();
});
