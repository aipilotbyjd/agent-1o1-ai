<?php

namespace App\Models\Agents;

use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Database\Factories\Agents\DocumentEmbeddingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'collection', 'source', 'chunk_index', 'chunk_text', 'embedding', 'metadata'])]
class DocumentEmbedding extends Model
{
    /** @use HasFactory<DocumentEmbeddingFactory> */
    use HasFactory, HasUuids;

    /**
     * Collections starting with this prefix are written by the app itself
     * (`Agent::artifactKnowledgeCollection()`), not chosen by workspace members.
     */
    public const string ARTIFACT_COLLECTION_PREFIX = 'artifacts:';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'collection' => 'default',
        'chunk_index' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'embedding' => 'array',
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
