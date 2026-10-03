<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Workspaces\Workspace;

/**
 * Which `document_embeddings.collection`s an agent may search — Gumloop's
 * "Knowledge Sources" agent-configuration section (see
 * docs/gumloop/output/raw/core-concepts/brain.md). Attaching a collection
 * here is what scopes `ToolRegistry`'s `SearchKnowledgeTool`/
 * `ReadKnowledgeDocumentTool` to it instead of the whole workspace.
 */
class AgentKnowledgeSourceController extends Controller
{
    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success([
            'attached' => $agent->knowledgeCollections()->pluck('collection')->values(),
            // Every collection in the workspace, so a picker can offer what
            // isn't attached yet. Agents' exported-artifact collections are
            // private to the agent that wrote them, so they aren't offered.
            'available' => DocumentEmbedding::query()
                ->where('workspace_id', $workspace->id)
                ->where('collection', 'not like', DocumentEmbedding::ARTIFACT_COLLECTION_PREFIX.'%')
                ->distinct()
                ->orderBy('collection')
                ->pluck('collection'),
        ]);
    }

    public function store(Workspace $workspace, Agent $agent, string $collection)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        abort_if(
            str_starts_with($collection, DocumentEmbedding::ARTIFACT_COLLECTION_PREFIX)
                || ! DocumentEmbedding::query()->where('workspace_id', $workspace->id)->where('collection', $collection)->exists(),
            404,
            'No knowledge collection with that name exists in this workspace.',
        );

        $agent->knowledgeCollections()->firstOrCreate(['collection' => $collection]);

        return ApiResponse::success([
            'attached' => $agent->knowledgeCollections()->pluck('collection')->values(),
        ], 'Knowledge source attached.');
    }

    public function destroy(Workspace $workspace, Agent $agent, string $collection)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $agent->knowledgeCollections()->where('collection', $collection)->delete();

        return ApiResponse::noContent();
    }
}
