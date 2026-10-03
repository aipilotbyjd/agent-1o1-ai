<?php

namespace App\Http\Controllers\Api\Internal\V1\KnowledgeBase;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\KnowledgeBase\IngestKnowledgeRequest;
use App\Http\Requests\Api\Internal\V1\KnowledgeBase\ReadKnowledgeDocumentRequest;
use App\Http\Requests\Api\Internal\V1\KnowledgeBase\SearchKnowledgeRequest;
use App\Http\Resources\Api\Internal\V1\KnowledgeBase\DocumentEmbeddingResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\KnowledgeBase;
use App\Services\Billing\CreditGate;
use Illuminate\Http\Request;

/**
 * The workspace-wide knowledge base agents retrieve from: text ingested here
 * is chunked, embedded, and stored in `document_embeddings`, which
 * `ToolRegistry` turns into a `SearchKnowledgeTool` for every agent in the
 * workspace as soon as a single chunk exists. The per-agent, always-injected
 * counterpart is `AgentKnowledgeController` — see docs/AGENTS_PLAN.md's
 * "Knowledge / RAG" section for why both exist.
 *
 * Every member can also keep private knowledge here (their Brain): chunks
 * with `owner_id` set are visible only to that member and their assistant,
 * never to agents or other members.
 */
class KnowledgeBaseController extends Controller
{
    public function __construct(private readonly KnowledgeBase $knowledgeBase) {}

    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        $chunks = DocumentEmbedding::query()
            ->visibleTo($request->user())
            ->where('workspace_id', $workspace->id)
            ->when($request->has('private'), fn ($query) => $request->boolean('private')
                ? $query->whereNotNull('owner_id')
                : $query->whereNull('owner_id'))
            ->when($request->query('collection'), fn ($query, $collection) => $query->where('collection', $collection))
            ->when($request->query('source'), fn ($query, $source) => $query->where('source', $source))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 25));

        return ApiResponse::paginated(DocumentEmbeddingResource::collection($chunks));
    }

    /**
     * Collections are just a string column, so the catalog is derived rather
     * than stored — this is what a picker needs to offer the existing ones.
     */
    public function collections(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        $collections = DocumentEmbedding::query()
            ->visibleTo($request->user())
            ->where('workspace_id', $workspace->id)
            ->selectRaw('collection, (owner_id IS NOT NULL) as is_private, COUNT(*) as chunks_count')
            ->groupBy('collection', 'is_private')
            ->orderBy('collection')
            ->get()
            ->map(fn (DocumentEmbedding $row): array => [
                'collection' => $row->collection,
                'private' => (bool) $row->is_private,
                'chunks_count' => (int) $row->chunks_count,
            ]);

        return ApiResponse::success(['collections' => $collections]);
    }

    /**
     * Embedding is billed (see `KnowledgeBase::ingest()`), so a workspace out
     * of credits is refused before the provider is called.
     */
    public function store(IngestKnowledgeRequest $request, Workspace $workspace, CreditGate $creditGate)
    {
        $private = $request->boolean('private');

        // Anyone may keep private knowledge; shared knowledge reaches every agent.
        $this->requirePermission($private ? Permission::AgentView : Permission::AgentManage);

        $file = $request->file('file');
        $text = $file !== null ? (string) file_get_contents($file->getRealPath()) : (string) $request->validated('text');

        abort_if(trim($text) === '', 422, 'The file has no readable text.');

        $creditGate->assertCanStartRun($workspace);

        $chunks = $this->knowledgeBase->ingest(
            $workspace,
            $text,
            $request->validated('source') ?? $file?->getClientOriginalName(),
            $request->validated('collection') ?? ($private ? 'personal' : 'default'),
            $request->validated('metadata'),
            ownerId: $private ? $request->user()->id : null,
        );

        return ApiResponse::created([
            'chunks_count' => $chunks->count(),
            'chunks' => DocumentEmbeddingResource::collection($chunks),
        ], 'Knowledge ingested.');
    }

    /**
     * Runs the exact retrieval `Ai\Tools\ReadKnowledgeDocumentTool` would,
     * so a workspace can preview a document's full text without starting a
     * conversation — the "read document" counterpart to `search()`.
     */
    public function document(ReadKnowledgeDocumentRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        $text = $this->knowledgeBase->readDocument(
            $workspace,
            $request->validated('source'),
            $request->validated('collection'),
            $request->user(),
        );

        abort_if($text === null, 404, 'No document found for that source.');

        return ApiResponse::success([
            'source' => $request->validated('source'),
            'text' => $text,
        ]);
    }

    /**
     * Runs the exact retrieval an agent's `SearchKnowledgeTool` would, so a
     * workspace can see what its agents will actually find for a question
     * without starting a conversation.
     */
    public function search(SearchKnowledgeRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        $results = $this->knowledgeBase->search(
            $workspace,
            $request->validated('query'),
            $request->validated('collection'),
            (int) ($request->validated('limit') ?? KnowledgeBase::DEFAULT_TOP_N),
            $request->user(),
        );

        return ApiResponse::success([
            'results' => $results->map(fn (array $result): array => [
                ...$result,
                'score' => round($result['score'], 4),
            ]),
        ]);
    }

    public function destroy(Request $request, Workspace $workspace, DocumentEmbedding $documentEmbedding)
    {
        $this->ensureBelongsToWorkspace($workspace, $documentEmbedding);
        $this->authorizeChange($request, $documentEmbedding->owner_id);

        $documentEmbedding->delete();

        return ApiResponse::noContent();
    }

    /**
     * Deleting a whole collection is the practical way to re-ingest a source
     * document: drop the collection, ingest the new revision.
     */
    public function destroyCollection(Request $request, Workspace $workspace, string $collection)
    {
        $private = $request->boolean('private');
        $this->requirePermission($private ? Permission::AgentView : Permission::AgentManage);

        $deleted = DocumentEmbedding::query()
            ->where('workspace_id', $workspace->id)
            ->where('collection', $collection)
            ->when($private, fn ($query) => $query->where('owner_id', $request->user()->id), fn ($query) => $query->shared())
            ->delete();

        abort_if($deleted === 0, 404);

        return ApiResponse::success(['deleted_count' => $deleted], 'Collection deleted.');
    }

    /**
     * Private knowledge is changed only by its owner (anyone else gets 404,
     * as if it didn't exist); shared knowledge needs `agent.manage`.
     */
    private function authorizeChange(Request $request, ?string $ownerId): void
    {
        if ($ownerId === null) {
            $this->requirePermission(Permission::AgentManage);

            return;
        }

        abort_if($ownerId !== $request->user()->id, 404);
    }
}
