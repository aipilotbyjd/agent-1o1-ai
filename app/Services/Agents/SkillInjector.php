<?php

namespace App\Services\Agents;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentMemory;

/**
 * Composes an `Agent`'s base `instructions` with its attached `Skill`s,
 * active `AgentKnowledge` entries, and remembered `AgentMemory` facts into
 * one final system-prompt string — see docs/AGENTS_PLAN.md's "Skills"
 * section ("no config binding, just injected as additional system-prompt
 * context alongside instructions()"), "Knowledge / RAG" (`AgentKnowledge` is
 * "always know this", injected directly, as opposed to
 * `SearchKnowledgeTool`'s "look this up when relevant"), and the
 * `agent_memories` section ("durable memory distinct from `AgentSession`'s
 * per-conversation history").
 */
class SkillInjector
{
    public function instructionsFor(Agent $agent, ?int $userId = null): string
    {
        $sections = [$agent->instructions];

        foreach ($agent->skills as $skill) {
            $sections[] = "## Skill: {$skill->name}\n{$skill->instructions}";
        }

        foreach ($agent->knowledge()->where('is_active', true)->orderBy('sort_order')->get() as $knowledge) {
            if ($knowledge->content !== null) {
                $sections[] = "## Knowledge: {$knowledge->title}\n{$knowledge->content}";
            }
        }

        if ($memories = $this->memoriesSection($agent, $userId)) {
            $sections[] = $memories;
        }

        return implode("\n\n", $sections);
    }

    /**
     * Scoped to the run's user when known, plus workspace-wide (user_id
     * null) memories — a memory tied to a specific user shouldn't leak into
     * another user's conversation.
     */
    private function memoriesSection(Agent $agent, ?int $userId): ?string
    {
        $entries = $agent->memories()
            ->where(fn ($q) => $q->whereNull('user_id')->when($userId, fn ($q) => $q->orWhere('user_id', $userId)))
            ->orderBy('key')
            ->get();

        if ($entries->isEmpty()) {
            return null;
        }

        $body = $entries
            ->map(fn (AgentMemory $entry): string => "- {$entry->key}: {$entry->value}")
            ->implode("\n");

        return "## Things you remember\n{$body}";
    }
}
