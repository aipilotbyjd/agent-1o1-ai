<?php

namespace App\Http\Controllers\Api\Internal\V1\Artifacts;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Artifacts\ArtifactResource;
use App\Http\Responses\ApiResponse;
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

        return Storage::disk($artifact->disk)->download($artifact->path, $artifact->filename);
    }

    public function preview(Request $request, Artifact $artifact): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired signature.');
        abort_unless($artifact->isPreviewable(), 404);

        return Storage::disk($artifact->disk)->response($artifact->path, $artifact->filename, [
            'Content-Type' => $artifact->mime_type,
        ]);
    }
}
