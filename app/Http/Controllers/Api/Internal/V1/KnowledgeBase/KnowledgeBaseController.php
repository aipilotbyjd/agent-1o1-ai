<?php

namespace App\Http\Controllers\Api\Internal\V1\KnowledgeBase;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\KnowledgeBase\IngestKnowledgeRequest;
use App\Http\Requests\Api\Internal\V1\KnowledgeBase\SearchKnowledgeRequest;
use App\Http\Resources\Api\Internal\V1\KnowledgeBase\DocumentEmbeddingResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\KnowledgeBase;
use Illuminate\Http\Request;

/**
 * The workspace-wide knowledge base agents retrieve from: text ingested here
 * is chunked, embedded, and stored in `document_embeddings`, which
 * `ToolRegistry` turns into a `SearchKnowledgeTool` for every agent in the
 * workspace as soon as a single chunk exists. The per-agent, always-injected
 * counterpart is `AgentKnowledgeController` — see docs/AGENTS_PLAN.md's
 * "Knowledge / RAG" section for why both exist.
 */
class KnowledgeBaseController extends Controller
{
    public function __construct(private readonly KnowledgeBase $knowledgeBase) {}

    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        $chunks = DocumentEmbedding::query()
            ->where('workspace_id', $workspace->id)
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
    public function collections(Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        $collections = DocumentEmbedding::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('collection, COUNT(*) as chunks_count')
            ->groupBy('collection')
            ->orderBy('collection')
            ->get()
            ->map(fn (DocumentEmbedding $row): array => [
                'collection' => $row->collection,
                'chunks_count' => (int) $row->chunks_count,
            ]);

        return ApiResponse::success(['collections' => $collections]);
    }

    public function store(IngestKnowledgeRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentManage);

        $chunks = $this->knowledgeBase->ingest(
            $workspace,
            $request->validated('text'),
            $request->validated('source'),
            $request->validated('collection') ?? 'default',
            $request->validated('metadata'),
        );

        return ApiResponse::created([
            'chunks_count' => $chunks->count(),
            'chunks' => DocumentEmbeddingResource::collection($chunks),
        ], 'Knowledge ingested.');
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
        );

        return ApiResponse::success([
            'results' => $results->map(fn (array $result): array => [
                ...$result,
                'score' => round($result['score'], 4),
            ]),
        ]);
    }

    public function destroy(Workspace $workspace, DocumentEmbedding $documentEmbedding)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $documentEmbedding);

        $documentEmbedding->delete();

        return ApiResponse::noContent();
    }

    /**
     * Deleting a whole collection is the practical way to re-ingest a source
     * document: drop the collection, ingest the new revision.
     */
    public function destroyCollection(Workspace $workspace, string $collection)
    {
        $this->requirePermission(Permission::AgentManage);

        $deleted = DocumentEmbedding::query()
            ->where('workspace_id', $workspace->id)
            ->where('collection', $collection)
            ->delete();

        abort_if($deleted === 0, 404);

        return ApiResponse::success(['deleted_count' => $deleted], 'Collection deleted.');
    }
}
