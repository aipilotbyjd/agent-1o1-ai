<?php

namespace App\Models\Agents;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agent_id', 'collection'])]
class AgentKnowledgeCollection extends Model
{
    use HasUuids;

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
