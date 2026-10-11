<?php

namespace App\Models\Workflows;

use App\Enums\Workflows\PageAccess;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Workflows\WorkflowPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A page built on top of a workflow: a form for its inputs and a place for
 * its result, so people can use the workflow without opening the canvas.
 *
 * `content` is the draft the builder edits; `published_content` is what
 * visitors see, copied from the draft on publish — the same split workflows
 * have, so an edit in progress never shows on a live page. Both are always
 * in the canonical shape `PageSchema::normalize()` returns.
 *
 * `public_token` is the page's link. It exists for every page, but only
 * answers when `access` is public; rotating it breaks the old link.
 */
#[Fillable(['workspace_id', 'workflow_id', 'created_by', 'name', 'description', 'content', 'access', 'is_enabled', 'max_runs_per_day'])]
#[Hidden(['password'])]
class WorkflowPage extends Model
{
    /** @use HasFactory<WorkflowPageFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'access' => 'workspace',
        'is_enabled' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $page): void {
            $page->public_token ??= self::newToken();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'published_content' => 'array',
            'published_at' => 'datetime',
            'access' => PageAccess::class,
            'password' => 'hashed',
            'is_enabled' => 'boolean',
            'max_runs_per_day' => 'integer',
        ];
    }

    public static function newToken(): string
    {
        return Str::random(32);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(WorkflowPageSubmission::class);
    }

    public function isPublished(): bool
    {
        return $this->published_content !== null;
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->published_content === null
            || json_encode($this->content) !== json_encode($this->published_content);
    }

    /**
     * Whether the page answers at its public link right now.
     */
    public function isPubliclyAvailable(): bool
    {
        return $this->is_enabled && $this->access->isPublic() && $this->isPublished();
    }
}
