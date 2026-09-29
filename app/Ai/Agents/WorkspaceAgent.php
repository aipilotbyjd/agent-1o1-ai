<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\AppliesGenerationSettings;
use App\Enums\Agents\AgentMessageRole;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Services\Agents\GenerationSettings;
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
    use AppliesGenerationSettings, Promptable;

    /**
     * How many of the most recent messages are sent as prior context. The
     * full transcript stays in `agent_messages`; only what the provider sees
     * is bounded, so a long conversation can't grow past the context window.
     */
    public const int HISTORY_LIMIT = 50;

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
        private readonly ?GenerationSettings $settings = null,
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
     * The latest `HISTORY_LIMIT` user/assistant messages, oldest first.
     *
     * A user message with no reply after it belongs to a turn that failed —
     * it is kept in the transcript but left out here, so the model isn't
     * handed a question it never answered. The window is also trimmed to
     * start on a user message, since a cut can land mid-exchange.
     *
     * @return iterable<int, Message>
     */
    public function messages(): iterable
    {
        $history = $this->session->messages()
            ->whereIn('role', [AgentMessageRole::User, AgentMessageRole::Assistant])
            ->when($this->beforeMessageId !== null, fn ($query) => $query->where('id', '!=', $this->beforeMessageId))
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values();

        $answered = $history->filter(function (AgentMessage $message, int $index) use ($history): bool {
            return $message->role !== AgentMessageRole::User
                || $history->get($index + 1)?->role === AgentMessageRole::Assistant;
        });

        return $answered
            ->skipUntil(fn (AgentMessage $message): bool => $message->role === AgentMessageRole::User)
            ->map(fn (AgentMessage $message) => new Message($message->role->value, $message->content))
            ->values()
            ->all();
    }
}
