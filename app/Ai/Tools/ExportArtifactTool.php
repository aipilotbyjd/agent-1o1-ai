<?php

namespace App\Ai\Tools;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Artifacts\Artifact;
use App\Models\Runs\Run;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lets an agent export a generated file (report, image, code, spreadsheet,
 * HTML, etc.) as a downloadable, versioned `Artifact`. Re-exporting the same
 * filename within the same session creates a new version instead of
 * overwriting — see `Artifact::versions()`/`group_id`. Auto-attached to
 * every agent by `ToolRegistry`, same as `RememberTool`.
 */
class ExportArtifactTool implements Tool
{
    public function __construct(
        private readonly Agent $agent,
        private readonly AgentSession $session,
        private readonly Run $run,
    ) {}

    public function description(): Stringable|string
    {
        return 'Export a file you generated (report, image, code, spreadsheet, HTML dashboard, etc.) as a '
            .'downloadable artifact. Provide a filename, mime_type, and the file content (UTF-8 text, or '
            .'base64 when is_base64 is true). Re-using the same filename in this conversation creates a new '
            .'version instead of overwriting the previous one. '
            .'IMPORTANT: you must call this tool every single time you export or re-export a file, including '
            .'when a user asks you to regenerate, update, or redo a file you already exported earlier in this '
            .'conversation. Never claim a file was exported or a new version was created unless you actually '
            .'called this tool for that exact request — a fresh call is required each time, even if the content '
            .'or filename is unchanged.';
    }

    public function handle(Request $request): Stringable|string
    {
        $filename = (string) ($request['filename'] ?? '');
        $mimeType = (string) ($request['mime_type'] ?? '');
        $content = (string) ($request['content'] ?? '');
        $isBase64 = (bool) ($request['is_base64'] ?? false);

        if ($filename === '' || $mimeType === '' || $content === '') {
            return json_encode(['error' => 'filename, mime_type, and content are required.']);
        }

        $decoded = $isBase64 ? base64_decode($content, true) : $content;

        if ($decoded === false) {
            return json_encode(['error' => 'content is not valid base64.']);
        }

        $previous = Artifact::query()
            ->where('workspace_id', $this->agent->workspace_id)
            ->where('agent_session_id', $this->session->id)
            ->where('filename', $filename)
            ->orderByDesc('version')
            ->first();

        $groupId = $previous?->group_id ?? (string) Str::uuid();
        $version = $previous ? $previous->version + 1 : 1;

        $path = "artifacts/{$this->agent->workspace_id}/{$groupId}/v{$version}-{$filename}";
        Storage::disk('local')->put($path, $decoded);

        $artifact = Artifact::create([
            'workspace_id' => $this->agent->workspace_id,
            'agent_id' => $this->agent->id,
            'agent_session_id' => $this->session->id,
            'run_id' => $this->run->id,
            'created_by' => $this->session->user_id,
            'group_id' => $groupId,
            'version' => $version,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size' => strlen($decoded),
            'disk' => 'local',
            'path' => $path,
        ]);

        return json_encode([
            'id' => $artifact->id,
            'group_id' => $artifact->group_id,
            'filename' => $artifact->filename,
            'version' => $artifact->version,
            'mime_type' => $artifact->mime_type,
            'size' => $artifact->size,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()->required(),
            'mime_type' => $schema->string()->required(),
            'content' => $schema->string()->required(),
            'is_base64' => $schema->boolean(),
        ];
    }
}
