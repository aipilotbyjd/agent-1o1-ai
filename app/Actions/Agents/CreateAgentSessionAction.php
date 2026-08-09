<?php

namespace App\Actions\Agents;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\User;

class CreateAgentSessionAction
{
    public function execute(Agent $agent, ?User $user = null, ?string $title = null): AgentSession
    {
        return $agent->sessions()->create([
            'workspace_id' => $agent->workspace_id,
            'user_id' => $user?->id,
            'title' => $title,
        ]);
    }
}
