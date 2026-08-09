<?php

namespace App\Models\Agents;

use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'collection', 'source', 'chunk_text', 'embedding', 'metadata'])]
class DocumentEmbedding extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'collection' => 'default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
