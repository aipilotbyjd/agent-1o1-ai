<?php

use App\Ai\Tools\SearchKnowledgeTool;
use App\Models\Agents\DocumentEmbedding;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;

it('ranks the closest-by-construction chunk first', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    // Query vector points along the first axis; "close" shares that
    // direction, "far" is orthogonal (cosine similarity 0), "opposite"
    // points the other way entirely (cosine similarity -1).
    Embeddings::fake([[[1.0, 0.0, 0.0]]]);

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 'close', 'chunk_text' => 'close chunk', 'embedding' => [0.9, 0.1, 0.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 'far', 'chunk_text' => 'far chunk', 'embedding' => [0.0, 1.0, 0.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 'opposite', 'chunk_text' => 'opposite chunk', 'embedding' => [-1.0, 0.0, 0.0]]);

    $tool = new SearchKnowledgeTool($workspace);
    $result = json_decode($tool->handle(new Request(['query' => 'anything'])), true);

    expect($result[0]['source'])->toBe('close');
    expect($result[0]['score'])->toBeGreaterThan($result[1]['score']);
    expect($result[1]['score'])->toBeGreaterThan($result[2]['score']);
    expect($result[2]['source'])->toBe('opposite');
});

it('scopes results to the given collection', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Embeddings::fake([[[1.0, 0.0]]]);

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'docs', 'source' => 'in-collection', 'chunk_text' => 'a', 'embedding' => [1.0, 0.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'other', 'source' => 'out-of-collection', 'chunk_text' => 'b', 'embedding' => [1.0, 0.0]]);

    $tool = new SearchKnowledgeTool($workspace, collection: 'docs');
    $result = json_decode($tool->handle(new Request(['query' => 'anything'])), true);

    expect($result)->toHaveCount(1);
    expect($result[0]['source'])->toBe('in-collection');
});

it('does not leak results from another workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherWorkspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);

    Embeddings::fake([[[1.0, 0.0]]]);

    DocumentEmbedding::create(['workspace_id' => $otherWorkspace->id, 'source' => 'foreign', 'chunk_text' => 'c', 'embedding' => [1.0, 0.0]]);

    $tool = new SearchKnowledgeTool($workspace);
    $result = json_decode($tool->handle(new Request(['query' => 'anything'])), true);

    expect($result)->toBe([]);
});
