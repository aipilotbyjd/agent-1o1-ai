<?php

namespace App\Models\Agents;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agent_id', 'title', 'content', 'source_type', 'source_url', 'file_path', 'tokens', 'is_active', 'sort_order', 'metadata'])]
class AgentKnowledge extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source_type' => 'text',
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tokens' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
