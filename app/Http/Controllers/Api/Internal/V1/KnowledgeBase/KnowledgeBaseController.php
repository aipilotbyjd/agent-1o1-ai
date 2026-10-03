<?php

namespace App\Http\Controllers\Api\Internal\V1\KnowledgeBase;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\KnowledgeBase\IngestKnowledgeRequest;
use App\Http\Requests\Api\Internal\V1\KnowledgeBase\ReadKnowledgeDocumentRequest;
use App\Http\Requests\Api\Internal\V1\KnowledgeBase\SearchKnowledgeRequest;
use App\Http\Resources\Api\Internal\V1\KnowledgeBase\DocumentEmbeddingResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\Knowledge\IngestKnowledgeJob;
use App\Models\Agents\AgentKnowledgeCollection;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\KnowledgeBase;
use App\Services\Billing\CreditGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->orderBy('collection')
            ->orderBy('source')
            ->orderBy('chunk_index')
            ->orderBy('id')
            ->paginate(max(1, min((int) $request->query('per_page', 25), 100)));

        return ApiResponse::paginated(DocumentEmbeddingResource::collection($chunks));
    }

    /**
     * Collections are just a string column, so the catalog is derived rather
     * than stored — this is what a picker needs to offer the existing ones.
     * Agents' own exported-artifact collections are internal and left out.
     */
    public function collections(Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        $collections = DocumentEmbedding::query()
            ->where('workspace_id', $workspace->id)
            ->where('collection', 'not like', DocumentEmbedding::ARTIFACT_COLLECTION_PREFIX.'%')
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

    /**
     * Embedding is billed (see `KnowledgeBase::ingest()`), so a workspace out
     * of credits is refused before the provider is called. Short text is
     * embedded during the request; longer text is queued (202), since
     * embedding a multi-megabyte file outlasts an HTTP request.
     *
     * Ingesting a `source` that already exists in the collection replaces it
     * — re-uploading a revised file never duplicates its chunks.
     */
    public function store(IngestKnowledgeRequest $request, Workspace $workspace, CreditGate $creditGate)
    {
        $this->requirePermission(Permission::AgentManage);

        $file = $request->file('file');
        $text = $file !== null ? (string) file_get_contents($file->getRealPath()) : (string) $request->validated('text');
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;

        abort_if(trim($text) === '', 422, 'The file has no readable text.');
        abort_unless(mb_check_encoding($text, 'UTF-8'), 422, 'The content must be UTF-8 text.');

        $creditGate->assertCanStartRun($workspace);

        $source = $request->validated('source') ?? $file?->getClientOriginalName();
        $collection = $request->validated('collection') ?? 'default';
        $metadata = $request->validated('metadata');

        if (mb_strlen($text) > (int) config('knowledge_base.sync_ingest_max_characters')) {
            IngestKnowledgeJob::dispatch($workspace, $text, $source, $collection, $metadata);

            return ApiResponse::success(['queued' => true], 'Knowledge ingestion queued.', 202);
        }

        $chunks = $this->knowledgeBase->ingest($workspace, $text, $source, $collection, $metadata, replaceSource: true);

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
        );

        return ApiResponse::success([
            'results' => $results->map(fn (array $result): array => [
                ...$result,
                'score' => round($result['score'], 4),
            ]),
        ]);
    }

    /**
     * Removes a whole document — every chunk sharing a source — so a file
     * can be taken out without deleting its collection.
     */
    public function destroyDocument(ReadKnowledgeDocumentRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentManage);

        $deleted = $this->knowledgeBase->deleteDocument(
            $workspace,
            $request->validated('source'),
            $request->validated('collection'),
        );

        abort_if($deleted === 0, 404, 'No document found for that source.');

        return ApiResponse::success(['deleted_count' => $deleted], 'Document deleted.');
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

        $deleted = DB::transaction(function () use ($workspace, $collection): int {
            $deleted = DocumentEmbedding::query()
                ->where('workspace_id', $workspace->id)
                ->where('collection', $collection)
                ->delete();

            // An agent left attached to a collection that no longer exists
            // would keep a search tool scoped to nothing instead of falling
            // back to the workspace — detach it along with the data.
            if ($deleted > 0) {
                AgentKnowledgeCollection::query()
                    ->where('collection', $collection)
                    ->whereIn('agent_id', $workspace->agents()->select('agents.id'))
                    ->delete();
            }

            return $deleted;
        });

        abort_if($deleted === 0, 404);

        return ApiResponse::success(['deleted_count' => $deleted], 'Collection deleted.');
    }
}
