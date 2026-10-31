<?php

use App\Ai\Agents\WorkflowBuilderAgent;
use App\Ai\Agents\WorkflowBuilderTitleAgent;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\WorkspaceModelSettings;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->session = WorkflowBuilderSession::factory()->forWorkspace($this->workspace, $this->owner)->create();
    $this->url = "/api/v1/workspaces/{$this->workspace->id}/workflow-builder-sessions/{$this->session->id}/messages";
    $this->model = ModelCatalog::factory()->create(['slug' => 'claude-fast', 'display_name' => 'Claude Fast']);

    Passport::actingAs($this->owner);
});

it('stores the model the member picked for the turn and returns it', function () {
    Queue::fake();

    $response = $this->postJson($this->url, ['message' => 'Add a Slack step.', 'model_catalog_id' => $this->model->id])
        ->assertAccepted();

    $userMessage = WorkflowBuilderMessage::query()->where('role', 'user')->firstOrFail();

    expect($userMessage->model_catalog_id)->toBe($this->model->id);

    $this->getJson($this->url)->assertOk()
        ->assertJsonPath('data.messages.0.model_catalog_id', $this->model->id);

    Queue::assertPushed(ProcessWorkflowBuilderMessageJob::class);
    expect($response->json('data.message.role'))->toBe('assistant');
});

it('runs the turn on the picked model', function () {
    WorkflowBuilderAgent::fake(['Done.']);
    WorkflowBuilderTitleAgent::fake([]);

    $resolver = Mockery::mock(ModelCatalogResolver::class)->makePartial();
    $resolver->shouldReceive('providerChain')->with('claude-fast')->atLeast()->once()->andReturn(['openai' => 'gpt-4o']);
    app()->instance(ModelCatalogResolver::class, $resolver);

    $this->postJson($this->url, ['message' => 'Add a Slack step.', 'model_catalog_id' => $this->model->id])->assertAccepted();
});

it('runs on the assistant\'s own model when none is picked', function () {
    WorkflowBuilderAgent::fake(['Done.']);
    WorkflowBuilderTitleAgent::fake([]);

    $resolver = Mockery::mock(ModelCatalogResolver::class)->makePartial();
    $resolver->shouldReceive('providerChain')->with(ProcessWorkflowBuilderMessageJob::MODEL_CATALOG_SLUG)->atLeast()->once()->andReturn(['openai' => 'gpt-4o']);
    app()->instance(ModelCatalogResolver::class, $resolver);

    $this->postJson($this->url, ['message' => 'Add a Slack step.'])->assertAccepted();

    expect(WorkflowBuilderMessage::query()->where('role', 'user')->value('model_catalog_id'))->toBeNull();
});

it('refuses a model the builder may not use', function (string $state) {
    Queue::fake();

    $model = match ($state) {
        'internal' => ModelCatalog::factory()->create(['is_internal' => true]),
        'inactive' => ModelCatalog::factory()->create(['is_active' => false]),
        'unknown' => null,
    };

    $this->postJson($this->url, ['message' => 'Hi', 'model_catalog_id' => $model?->id ?? (string) Str::uuid()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('model_catalog_id');

    expect(WorkflowBuilderMessage::query()->count())->toBe(0);
})->with(['internal', 'inactive', 'unknown']);

it('refuses a model the workspace has not allowed', function () {
    Queue::fake();

    $allowed = ModelCatalog::factory()->create();
    WorkspaceModelSettings::query()->updateOrCreate(['workspace_id' => $this->workspace->id], ['allowed_model_catalog_ids' => [$allowed->id]]);

    $this->postJson($this->url, ['message' => 'Hi', 'model_catalog_id' => $this->model->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('model_catalog_id');

    $this->postJson($this->url, ['message' => 'Hi', 'model_catalog_id' => $allowed->id])->assertAccepted();
});
