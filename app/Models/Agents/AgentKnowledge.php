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

    /**
     * Rough token count for an entry's content, stored so a workspace can see
     * how much of an agent's prompt budget its knowledge is consuming. The
     * ~4-characters-per-token ratio is an estimate, not a tokenizer — it is
     * never used to bill anything, only to inform (`CreditMeter` charges on
     * the provider's own reported usage).
     */
    public static function estimateTokens(?string $content): ?int
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        return (int) ceil(mb_strlen($content) / 4);
    }
}
