<?php

use App\Actions\Workflows\Builder\SendWorkflowBuilderMessageAction;
use App\Ai\Agents\WorkflowBuilderAgent;
use App\Ai\Agents\WorkflowBuilderTitleAgent;
use App\Enums\Queue as QueueName;
use App\Enums\Workflows\BuilderMessageStatus;
use App\Enums\Workflows\BuilderSessionStatus;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;

it('accepts a chat message and writes the reply in the background', function () {
    WorkflowBuilderAgent::fake(["Sure, I've added the node."]);
    WorkflowBuilderTitleAgent::fake([]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages", [
        'message' => 'Add a node that sends a Slack message.',
    ]);

    $response->assertAccepted();
    expect($response->json('data.message.role'))->toBe('assistant');

    // The sync queue has already run the turn by the time the request returns.
    $reply = WorkflowBuilderMessage::find($response->json('data.message.id'));
    expect($reply->content)->toBe("Sure, I've added the node.");
    expect($reply->processing_status)->toBe(BuilderMessageStatus::Completed);
    expect($session->fresh()->messages)->toHaveCount(2);
});

it('returns the pending reply and queues the turn', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages", [
        'message' => 'Build me something.',
    ])->assertAccepted();

    expect($response->json('data.message.processing_status'))->toBe('pending');

    Queue::assertPushedOn(QueueName::WorkflowBuilder->value, ProcessWorkflowBuilderMessageJob::class, fn ($job) => $job->assistantMessageId === $response->json('data.message.id'));
});

it('refuses a second message while a reply is still being written', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $url = "/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages";

    Passport::actingAs($owner);

    $this->postJson($url, ['message' => 'first'])->assertAccepted();
    $this->postJson($url, ['message' => 'second'])->assertConflict();

    expect($session->messages()->count())->toBe(2);
});

it('fails a reply that has been in flight too long so the session is not stuck', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $url = "/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages";

    Passport::actingAs($owner);

    $stuck = $this->postJson($url, ['message' => 'first'])->json('data.message.id');

    $this->travel(SendWorkflowBuilderMessageAction::STALE_REPLY_MINUTES + 1)->minutes();

    $this->postJson($url, ['message' => 'second'])->assertAccepted();

    expect(WorkflowBuilderMessage::find($stuck)->processing_status)->toBe(BuilderMessageStatus::Failed);
});

it('refuses messages to an archived session', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create(['status' => BuilderSessionStatus::Archived]);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages", [
        'message' => 'hi',
    ])->assertConflict();
});

it('lists and shows a session\'s messages in order', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $base = "/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages";

    Passport::actingAs($owner);

    $replyId = $this->postJson($base, ['message' => 'hello'])->json('data.message.id');

    expect($this->getJson($base)->assertOk()->json('data.messages.*.role'))->toBe(['user', 'assistant']);
    $this->getJson("{$base}/{$replyId}")->assertOk()->assertJsonPath('data.message.processing_status', 'pending');
});

it('404s sending a message to a session in a different workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherOwner = User::factory()->create();
    $otherWorkspace = app(WorkspaceService::class)->create($otherOwner, ['name' => 'Other']);

    $foreign = WorkflowBuilderSession::factory()->forWorkspace($otherWorkspace, $otherOwner)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$foreign->id}/messages", [
        'message' => 'hi',
    ])->assertNotFound();
});
