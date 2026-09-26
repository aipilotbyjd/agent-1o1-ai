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
    public function instructionsFor(Agent $agent, ?string $userId = null): string
    {
        $sections = $this->injectedSections($agent, $userId);

        return implode("\n\n", array_filter([array_shift($sections), $agent->instructions, ...$sections], filled(...)));
    }

    /**
     * Everything added to the base instructions, exactly as it appears in the
     * final prompt — so `UpdateInstructionsTool` can strip any of it a model
     * copies back into its own instructions.
     *
     * @return array<int, string>
     */
    public function injectedSections(Agent $agent, ?string $userId = null): array
    {
        $sections = [$this->aboutSection($agent)];

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

        return $sections;
    }

    /**
     * Who the agent is and how it should behave. Without it the model only
     * sees the user-written instructions, which are often generic, and
     * introduces itself as the underlying model instead of this agent.
     */
    private function aboutSection(Agent $agent): string
    {
        $lines = [
            "# About you\nYou are \"{$agent->name}\", an AI agent built in LinkFlow.",
        ];

        if (filled($agent->description)) {
            $lines[] = "Your purpose: {$agent->description}";
        }

        $lines[] = 'Today is '.now()->format('l, F j, Y').'.';
        $lines[] = "When asked who you are, answer as {$agent->name}; never present yourself as the underlying language model or its provider.";
        $lines[] = 'Act on the most likely intent of each request, and ask a clarifying question only when a wrong guess would be costly. '
            .'When you have tools that can get real data or do the work, use them instead of guessing, and chain several calls when a task needs it.';
        $lines[] = $agent->allow_self_updates
            ? 'When the user corrects you or sets a rule that should apply from now on, update your own instructions.'
            : 'You cannot change your own instructions. If the user wants a lasting change to how you behave, tell them to edit your instructions in this agent\'s settings, or to turn on self-updates there.';

        return implode("\n", $lines);
    }

    /**
     * Scoped to the run's user when known, plus workspace-wide (user_id
     * null) memories — a memory tied to a specific user shouldn't leak into
     * another user's conversation.
     */
    private function memoriesSection(Agent $agent, ?string $userId): ?string
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
