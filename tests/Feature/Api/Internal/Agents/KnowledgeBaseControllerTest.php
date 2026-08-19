<?php

use App\Ai\Tools\SearchKnowledgeTool;
use App\Enums\Workspaces\Role;
use App\Models\Agents\DocumentEmbedding;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForKnowledgeBase(): array
{
    $owner = User::factory()->create();

    return [app(WorkspaceService::class)->create($owner, ['name' => 'Acme']), $owner];
}

/**
 * A paragraph long enough that the chunker can't pack it together with
 * another one — the splitter targets ~1,000 characters per chunk.
 */
function longParagraph(string $opening): string
{
    return $opening.' '.str_repeat('Supporting policy detail. ', 30);
}

it('ingests text as embedded chunks', function () {
    Embeddings::fake([[[1.0, 0.0], [0.0, 1.0]]]);
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $refunds = longParagraph('First paragraph about refunds.');
    $shipping = longParagraph('Second paragraph about shipping.');

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", [
        'text' => "{$refunds}\n\n{$shipping}",
        'source' => 'handbook.md',
        'collection' => 'support',
        'metadata' => ['revision' => 3],
    ]);

    $response->assertCreated();
    expect($response->json('data.chunks_count'))->toBe(2);

    $chunks = DocumentEmbedding::orderBy('id')->get();
    expect($chunks)->toHaveCount(2);
    expect($chunks[0]->chunk_text)->toStartWith('First paragraph about refunds.');
    expect($chunks[1]->chunk_text)->toStartWith('Second paragraph about shipping.');
    expect($chunks[0]->collection)->toBe('support');
    expect($chunks[0]->source)->toBe('handbook.md');
    // Loose comparison: a whole-number float round-trips through JSON as an int.
    expect($chunks[0]->embedding)->toEqual([1.0, 0.0]);
    expect($chunks[1]->embedding)->toEqual([0.0, 1.0]);
    expect($chunks[0]->metadata)->toBe(['revision' => 3]);

    // The vector is stored but never serialized back out — only its size is.
    expect($response->json('data.chunks.0'))->not->toHaveKey('embedding');
    expect($response->json('data.chunks.0.dimensions'))->toBe(2);

    Embeddings::assertGenerated(fn ($prompt) => count($prompt->inputs) === 2);
});

it('packs short paragraphs together and splits an over-long one', function () {
    Embeddings::fake();
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    // A 2,500-character paragraph can't fit one chunk and is split on its
    // sentence boundaries; the two short paragraphs ahead of it are packed
    // together rather than becoming two near-useless one-line chunks.
    $long = str_repeat('This sentence is repeated to overflow a single chunk. ', 48);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", [
        'text' => "Short one.\n\nShort two.\n\n{$long}",
    ]);

    $response->assertCreated();

    $chunks = DocumentEmbedding::orderBy('id')->get();

    expect($chunks[0]->chunk_text)->toStartWith("Short one.\n\nShort two.");
    expect($chunks->count())->toBeGreaterThan(2);
    // No chunk overshoots the target by more than a sentence.
    expect($chunks->max(fn (DocumentEmbedding $chunk) => mb_strlen($chunk->chunk_text)))->toBeLessThanOrEqual(1053);
    // Nothing is dropped on the way through the splitter.
    expect($chunks->sum(fn (DocumentEmbedding $chunk) => mb_strlen($chunk->chunk_text)))
        ->toBeGreaterThanOrEqual(mb_strlen(trim($long)));
});

it('searches with the same ranking an agent gets', function () {
    // One vector per call: the ingest of both chunks, then two query
    // embeddings — the API search, then the agent tool's search.
    Embeddings::fake([
        [[1.0, 0.0], [0.0, 1.0]],
        [[1.0, 0.0]],
        [[1.0, 0.0]],
    ]);
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", [
        'text' => longParagraph('Refund window is 30 days.')."\n\n".longParagraph('Shipping takes 5 days.'),
        'source' => 'handbook.md',
    ])->assertCreated();

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/search", [
        'query' => 'refunds',
        'limit' => 1,
    ]);

    $response->assertOk();
    expect($response->json('data.results'))->toHaveCount(1);
    expect($response->json('data.results.0.text'))->toStartWith('Refund window is 30 days.');
    expect($response->json('data.results.0.source'))->toBe('handbook.md');
    expect($response->json('data.results.0.score'))->toEqual(1.0);

    $toolResult = json_decode(app(SearchKnowledgeTool::class, ['workspace' => $workspace])
        ->handle(new Request(['query' => 'refunds'])), true);

    expect($toolResult[0]['text'])->toStartWith('Refund window is 30 days.');
});

it('scopes search to a collection', function () {
    Embeddings::fake([[[1.0, 0.0]], [[1.0, 0.0]], [[1.0, 0.0]]]);
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", ['text' => 'Support answer.', 'collection' => 'support']);
    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", ['text' => 'Sales answer.', 'collection' => 'sales']);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/search", [
        'query' => 'anything',
        'collection' => 'support',
    ]);

    $response->assertOk();
    expect($response->json('data.results'))->toHaveCount(1);
    expect($response->json('data.results.0.text'))->toBe('Support answer.');
});

it('lists collections with their chunk counts', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'support', 'chunk_text' => 'a', 'embedding' => [1.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'support', 'chunk_text' => 'b', 'embedding' => [1.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'sales', 'chunk_text' => 'c', 'embedding' => [1.0]]);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/collections");

    $response->assertOk();
    expect($response->json('data.collections'))->toBe([
        ['collection' => 'sales', 'chunks_count' => 1],
        ['collection' => 'support', 'chunks_count' => 2],
    ]);
});

it('deletes a single chunk and a whole collection', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $chunk = DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'support', 'chunk_text' => 'a', 'embedding' => [1.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'sales', 'chunk_text' => 'b', 'embedding' => [1.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'sales', 'chunk_text' => 'c', 'embedding' => [1.0]]);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/{$chunk->id}")->assertNoContent();

    $response = $this->deleteJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/collections/sales");

    $response->assertOk();
    expect($response->json('data.deleted_count'))->toBe(2);
    expect(DocumentEmbedding::count())->toBe(0);
});

it('does not touch another workspace\'s chunks', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    [$otherWorkspace] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $foreign = DocumentEmbedding::create([
        'workspace_id' => $otherWorkspace->id, 'collection' => 'support', 'chunk_text' => 'secret', 'embedding' => [1.0],
    ]);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/knowledge-base")->assertOk()
        ->assertJsonCount(0, 'data');

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/{$foreign->id}")->assertNotFound();
    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/collections/support")->assertNotFound();

    expect(DocumentEmbedding::count())->toBe(1);
});

it('does not let a viewer ingest', function () {
    [$workspace] = ownerWorkspaceForKnowledgeBase();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    Passport::actingAs($viewer);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", ['text' => 'a'])->assertForbidden();
    expect(DocumentEmbedding::count())->toBe(0);
});
