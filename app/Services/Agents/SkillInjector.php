<?php

namespace App\Services\Agents;

use App\Models\Agents\Agent;

/**
 * Composes an `Agent`'s base `instructions` with its attached `Skill`s and
 * active `AgentKnowledge` entries into one final system-prompt string — see
 * docs/AGENTS_PLAN.md's "Skills" section ("no config binding, just injected
 * as additional system-prompt context alongside instructions()") and
 * "Knowledge / RAG" (`AgentKnowledge` is "always know this", injected
 * directly, as opposed to `SearchKnowledgeTool`'s "look this up when
 * relevant").
 */
class SkillInjector
{
    public function instructionsFor(Agent $agent): string
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

        return implode("\n\n", $sections);
    }
}
