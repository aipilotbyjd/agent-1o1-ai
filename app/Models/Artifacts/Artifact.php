<?php

namespace App\Models\Artifacts;

use App\Enums\Artifacts\ArtifactGeneralAccess;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id', 'agent_id', 'agent_session_id', 'run_id', 'created_by',
    'group_id', 'version', 'filename', 'mime_type', 'size', 'disk', 'path', 'metadata',
    'general_access',
])]
class Artifact extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size' => 'integer',
            'metadata' => 'array',
            'general_access' => ArtifactGeneralAccess::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function agentSession(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Explicit per-user grants on this artifact's group — only consulted
     * when `general_access` is `restricted`.
     */
    public function shares(): HasMany
    {
        return $this->hasMany(ArtifactShare::class, 'group_id', 'group_id');
    }

    /**
     * All versions sharing this artifact's group_id, newest first.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'group_id', 'group_id')->orderByDesc('version');
    }

    /**
     * Restrict to only the newest version within each group_id.
     */
    public function scopeLatestPerGroup(Builder $query): Builder
    {
        return $query->joinSub(
            static::query()->selectRaw('group_id, MAX(version) as max_version')->groupBy('group_id'),
            'latest_versions',
            function ($join) {
                $join->on('artifacts.group_id', '=', 'latest_versions.group_id')
                    ->on('artifacts.version', '=', 'latest_versions.max_version');
            }
        );
    }

    private const PREVIEWABLE_MIME_TYPES = [
        'text/html',
        'application/pdf',
        'text/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function isPreviewable(): bool
    {
        return str_starts_with($this->mime_type, 'image/')
            || in_array($this->mime_type, self::PREVIEWABLE_MIME_TYPES, true);
    }

    /**
     * Whether `$user` may view this artifact. The creator and anyone with
     * `artifact.manage` (checked by the caller and passed in as
     * `$canManage`) always can; beyond that it follows `general_access` —
     * `restricted` narrows to an explicit `ArtifactShare` grant, while
     * `organization`/`anyone` defer to the workspace-level `artifact.view`
     * permission already enforced by the route.
     */
    public function isAccessibleBy(User $user, bool $canManage = false): bool
    {
        if ($canManage || $this->created_by === $user->id) {
            return true;
        }

        if ($this->general_access !== ArtifactGeneralAccess::Restricted) {
            return true;
        }

        return $this->shares()->where('user_id', $user->id)->exists();
    }
}
