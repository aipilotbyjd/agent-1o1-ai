<?php

namespace App\Actions\Artifacts;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Artifacts\Artifact;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Writes one artifact — the single place bytes land on disk and a row is
 * written, shared by the Internal API's upload endpoint and
 * `App\Ai\Tools\ExportArtifactTool` so "what does storing an artifact
 * actually do" (versioning, path layout, filename safety) has one
 * implementation.
 *
 * Versioning: a store never overwrites. It appends a new version to the
 * matching group — the group named by `$groupId`, or failing that the group
 * of the newest artifact with the same filename in the same scope (the
 * agent session for an agent export, the workspace's uploads for an upload)
 * — and starts a fresh group when there's nothing to match.
 */
class StoreArtifactAction
{
    /**
     * @param  string|UploadedFile  $contents  Raw bytes, or the uploaded file to stream to disk.
     * @param  array<string, mixed>|null  $metadata
     */
    public function execute(
        Workspace $workspace,
        string $filename,
        string $mimeType,
        string|UploadedFile $contents,
        ?Agent $agent = null,
        ?AgentSession $session = null,
        ?Run $run = null,
        ?int $createdBy = null,
        ?string $groupId = null,
        ?array $metadata = null,
    ): Artifact {
        $previous = Artifact::query()
            ->where('workspace_id', $workspace->id)
            ->when(
                $groupId !== null,
                fn ($query) => $query->where('group_id', $groupId),
                fn ($query) => $query
                    ->where('filename', $filename)
                    ->when(
                        $session !== null,
                        fn ($scoped) => $scoped->where('agent_session_id', $session->id),
                        fn ($scoped) => $scoped->whereNull('agent_session_id'),
                    ),
            )
            ->orderByDesc('version')
            ->first();

        $groupId = $previous?->group_id ?? $groupId ?? (string) Str::uuid();
        $version = $previous ? $previous->version + 1 : 1;

        $disk = (string) config('artifacts.disk');
        $path = "artifacts/{$workspace->id}/{$groupId}/v{$version}-{$this->safeName($filename)}";

        $this->write($disk, $path, $contents);

        return Artifact::create([
            'workspace_id' => $workspace->id,
            'agent_id' => $agent?->id,
            'agent_session_id' => $session?->id,
            'run_id' => $run?->id,
            'created_by' => $createdBy,
            'group_id' => $groupId,
            'version' => $version,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size' => $contents instanceof UploadedFile ? (int) $contents->getSize() : strlen($contents),
            'disk' => $disk,
            'path' => $path,
            'metadata' => $metadata,
        ]);
    }

    private function write(string $disk, string $path, string|UploadedFile $contents): void
    {
        if ($contents instanceof UploadedFile) {
            // Streams the temp file rather than pulling a multi-megabyte
            // upload through memory first.
            Storage::disk($disk)->putFileAs(dirname($path), $contents, basename($path));

            return;
        }

        Storage::disk($disk)->put($path, $contents);
    }

    /**
     * The path segment for a filename. The stored `filename` keeps whatever
     * the uploader (or the model, for an agent export) supplied; the path
     * never does — a name like `../../.env` would otherwise decide where the
     * bytes land.
     */
    private function safeName(string $filename): string
    {
        $name = basename(str_replace('\\', '/', $filename));
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $stem = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'file';

        return $extension === '' ? $stem : "{$stem}.".Str::slug($extension);
    }
}
