<?php

namespace App\Models\Agents;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agent_id', 'collection'])]
class AgentKnowledgeCollection extends Model
{
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
