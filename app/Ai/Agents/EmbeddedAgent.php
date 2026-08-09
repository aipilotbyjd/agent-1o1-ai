<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

/**
 * A single, stateless prompt against a workspace's `Agent` — what
 * `Nodes\AiAutomation\AgentNode` calls through (`AgentRunner::ask()`),
 * unlike `WorkspaceAgent`'s persisted, multi-turn `AgentSession` chat. No
 * `Conversational`: an embedded-in-workflow call has no session to load
 * history from or write a turn back to — see docs/AGENTS_PLAN.md's "Agent
 * turn loop" §2. `$instructions` is pre-composed by
 * `Services\Agents\SkillInjector`, same as `WorkspaceAgent`.
 */
class EmbeddedAgent implements Agent, HasTools
{
    use Promptable;

    /**
     * @param  array<int, Tool>  $tools
     */
    public function __construct(
        private readonly string $instructions,
        private readonly array $tools = [],
    ) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    /**
     * @return iterable<int, Tool>
     */
    public function tools(): iterable
    {
        return $this->tools;
    }
}
