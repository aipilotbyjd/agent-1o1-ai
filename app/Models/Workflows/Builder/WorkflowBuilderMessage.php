<?php

namespace App\Models\Workflows\Builder;

use Database\Factories\Workflows\Builder\WorkflowBuilderMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['session_id', 'draft_version_id', 'role', 'content', 'actions', 'processing_status', 'error_message'])]
class WorkflowBuilderMessage extends Model
{
    /** @use HasFactory<WorkflowBuilderMessageFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'processing_status' => 'completed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actions' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkflowBuilderSession::class, 'session_id');
    }

    public function draftVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowBuilderDraftVersion::class, 'draft_version_id');
    }
}
