<?php

namespace App\Ai\Agents;

use App\Models\Agents\AgentSession;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * Wraps an `AgentSession` as a `Laravel\Ai` agent — the standalone-chat path
 * from docs/AGENTS_PLAN.md's "Agent turn loop" section. `$instructions` is
 * pre-composed by `Services\Agents\SkillInjector` (base instructions +
 * attached Skills + active AgentKnowledge) and `$tools` by
 * `Services\Agents\ToolRegistry` — both handed in by `AgentRunner`; this
 * class has no opinion on either, only exposes what it's given.
 */
class WorkspaceAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * `$beforeMessageId` excludes the just-persisted user turn from
     * `messages()` — `AgentRunner` writes it to `agent_messages` before
     * building this class, and the SDK appends the live `prompt()` argument
     * as the latest user message itself, so including it here would
     * duplicate it in the context sent to the provider.
     *
     * @param  array<int, Tool>  $tools
     */
    public function __construct(
        private readonly string $instructions,
        private readonly AgentSession $session,
        private readonly ?int $beforeMessageId = null,
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

    /**
     * @return iterable<int, Message>
     */
    public function messages(): iterable
    {
        return $this->session->messages()
            ->when($this->beforeMessageId !== null, fn ($query) => $query->where('id', '!=', $this->beforeMessageId))
            ->oldest()
            ->get()
            ->map(fn ($message) => new Message($message->role->value, $message->content))
            ->all();
    }
}
