<?php

namespace App\Actions\Artifacts;

use App\Enums\Artifacts\ArtifactGeneralAccess;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Artifacts\Artifact;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\KnowledgeBase;
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
 *
 * When the artifact is filed under an agent and its content is
 * text-extractable, it is also indexed into that agent's
 * `Agent::artifactKnowledgeCollection()` — Gumloop's "Your agents'
 * artifacts" (docs/gumloop/output/raw/core-concepts/brain.md), letting the
 * agent search its own past outputs. Only the newest version stays
 * indexed, mirroring the doc's "updates in place" behavior.
 */
class StoreArtifactAction
{
    /**
     * Mime types read as UTF-8 text for indexing. Narrower than
     * `config('knowledge_base.allowed_extensions')` — this only decides
     * whether an *already-stored* artifact's bytes are safe to treat as
     * text, not what a knowledge-base upload accepts.
     *
     * @var array<int, string>
     */
    private const INDEXABLE_MIME_TYPES = [
        'text/plain', 'text/markdown', 'text/html', 'text/csv',
        'application/json', 'application/xml', 'text/xml', 'text/yaml', 'application/yaml',
    ];

    public function __construct(
        private readonly KnowledgeBase $knowledgeBase = new KnowledgeBase,
    ) {}

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
        // `withTrashed()`: a soft-deleted group must still be found here, both
        // to keep versioning past its last version number and so a matching
        // re-export restores it — see the soft-delete note on the migration.
        $previous = Artifact::withTrashed()
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

        if ($previous?->trashed()) {
            Artifact::withTrashed()->where('group_id', $previous->group_id)->restore();
        }

        $groupId = $previous?->group_id ?? $groupId ?? (string) Str::uuid();
        $version = $previous ? $previous->version + 1 : 1;

        $disk = (string) config('artifacts.disk');
        $path = "artifacts/{$workspace->id}/{$groupId}/v{$version}-{$this->safeName($filename)}";

        $this->write($disk, $path, $contents);

        $artifact = Artifact::create([
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
            // A new version keeps the group's existing sharing tier rather
            // than resetting to restricted.
            'general_access' => $previous?->general_access?->value ?? ArtifactGeneralAccess::Restricted->value,
        ]);

        if ($agent !== null && in_array($mimeType, self::INDEXABLE_MIME_TYPES, true)) {
            $this->indexForAgent($workspace, $agent, $artifact, $contents);
        }

        return $artifact;
    }

    private function indexForAgent(Workspace $workspace, Agent $agent, Artifact $artifact, string|UploadedFile $contents): void
    {
        $text = $contents instanceof UploadedFile
            ? (string) file_get_contents($contents->getRealPath())
            : $contents;

        if (trim($text) === '') {
            return;
        }

        $collection = $agent->artifactKnowledgeCollection();

        // Only the newest version stays indexed — drop the previous
        // version's chunks rather than accumulating stale ones.
        DocumentEmbedding::query()
            ->where('workspace_id', $workspace->id)
            ->where('collection', $collection)
            ->where('source', $artifact->filename)
            ->delete();

        $this->knowledgeBase->ingest(
            $workspace,
            $text,
            $artifact->filename,
            $collection,
            ['artifact_id' => $artifact->id, 'group_id' => $artifact->group_id],
        );
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
