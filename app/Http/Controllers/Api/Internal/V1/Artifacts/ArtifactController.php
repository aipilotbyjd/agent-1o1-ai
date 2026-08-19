<?php

namespace App\Http\Controllers\Api\Internal\V1\Artifacts;

use App\Actions\Artifacts\StoreArtifactAction;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Artifacts\UploadArtifactRequest;
use App\Http\Resources\Api\Internal\V1\Artifacts\ArtifactResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Artifacts\Artifact;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArtifactController extends Controller
{
    private const MIME_CATEGORIES = [
        'images' => ['image/'],
        'documents' => ['text/html', 'application/pdf'],
        'spreadsheets' => ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml'],
    ];

    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::ArtifactView);

        $artifacts = Artifact::query()
            ->where('workspace_id', $workspace->id)
            ->latestPerGroup()
            ->with(['agent', 'creator'])
            ->withCount('versions')
            ->when($request->query('search'), fn ($q, $search) => $q->where('filename', 'like', "%{$search}%"))
            ->when($request->query('agent_id'), fn ($q, $agentId) => $q->where('agent_id', $agentId))
            ->when($request->query('mime_category'), function ($q, $category) {
                $prefixes = self::MIME_CATEGORIES[$category] ?? null;

                if (! $prefixes) {
                    return $q;
                }

                return $q->where(function ($sub) use ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        $sub->orWhere('mime_type', 'like', "{$prefix}%");
                    }
                });
            })
            ->orderByDesc('artifacts.created_at')
            ->paginate((int) $request->query('per_page', 25));

        return ApiResponse::paginated(ArtifactResource::collection($artifacts));
    }

    public function store(UploadArtifactRequest $request, Workspace $workspace, StoreArtifactAction $storeArtifact)
    {
        $this->requirePermission(Permission::ArtifactManage);

        $file = $request->file('file');
        $agentId = $request->validated('agent_id');

        $artifact = $storeArtifact->execute(
            workspace: $workspace,
            filename: $request->validated('filename') ?? $file->getClientOriginalName(),
            // Guessed from the file's own bytes, not the client-supplied
            // Content-Type header — the stored value is what `preview()`
            // later serves the file as.
            mimeType: $file->getMimeType() ?? 'application/octet-stream',
            contents: $file,
            agent: $agentId === null ? null : Agent::findOrFail($agentId),
            createdBy: $request->user()->id,
            groupId: $request->validated('group_id'),
            metadata: $request->validated('metadata'),
        );

        return ApiResponse::created([
            'artifact' => ArtifactResource::make($artifact->load(['agent', 'creator'])),
        ], 'Artifact uploaded.');
    }

    public function show(Workspace $workspace, Artifact $artifact)
    {
        $this->requirePermission(Permission::ArtifactView);
        $this->ensureBelongsToWorkspace($workspace, $artifact);

        return ApiResponse::success([
            'artifact' => ArtifactResource::make($artifact->load(['agent', 'creator', 'versions'])),
        ]);
    }

    public function destroy(Workspace $workspace, Artifact $artifact)
    {
        $this->requirePermission(Permission::ArtifactManage);
        $this->ensureBelongsToWorkspace($workspace, $artifact);

        $group = Artifact::where('group_id', $artifact->group_id)->get();

        foreach ($group as $version) {
            Storage::disk($version->disk)->delete($version->path);
            $version->delete();
        }

        return ApiResponse::noContent();
    }

    public function download(Workspace $workspace, Artifact $artifact): StreamedResponse
    {
        $this->requirePermission(Permission::ArtifactView);
        $this->ensureBelongsToWorkspace($workspace, $artifact);

        return Storage::disk($artifact->disk)->download($artifact->path, $artifact->filename, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(Request $request, Artifact $artifact): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired signature.');
        abort_unless($artifact->isPreviewable(), 404);

        return Storage::disk($artifact->disk)->response($artifact->path, $artifact->filename, [
            'Content-Type' => $artifact->mime_type,
            // Artifacts are member- and model-supplied bytes served from this
            // application's own origin: an uploaded `text/html` artifact would
            // otherwise run as first-party script. The sandbox denies it
            // everything (scripts, forms, same-origin access) while still
            // rendering, and nosniff keeps the browser from re-deciding the
            // type for itself.
            'Content-Security-Policy' => 'sandbox',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
