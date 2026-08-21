<?php

namespace App\Observers;

use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Agents\AgentVersioner;
use Illuminate\Support\Facades\Auth;

/**
 * Keeps `agent_versions` honest without every write path having to remember
 * to snapshot — the same reasoning as `RunObserver`, and the same reason
 * `Workflow` gets away without one (a workflow's history is written at an
 * explicit publish step; an agent has no publish step).
 *
 * Being the single writer of history matters: `AgentVersioner::restore()`
 * deliberately does *not* snapshot for itself, because saving the restored
 * attributes lands here and would otherwise mint two versions for one edit.
 */
class AgentObserver
{
    public function __construct(private readonly AgentVersioner $versioner) {}

    public function created(Agent $agent): void
    {
        $this->versioner->snapshot($agent, $this->actor() ?? $agent->creator);
    }

    public function updated(Agent $agent): void
    {
        // Cosmetic edits (name, description) ride along in the next
        // behavioral snapshot instead of minting a version of their own —
        // see `AgentVersioner::BEHAVIORAL_ATTRIBUTES`.
        if ($agent->wasChanged(AgentVersioner::BEHAVIORAL_ATTRIBUTES)) {
            $this->versioner->snapshot($agent, $this->actor());
        }
    }

    /**
     * Whoever is making the edit, when there is one — an edit from a queued
     * job or a console command is attributed to nobody rather than guessed.
     */
    private function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
