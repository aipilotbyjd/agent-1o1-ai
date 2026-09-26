<?php

namespace App\Ai\Agents;

use App\Enums\Agents\AgentMessageRole;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Artifacts\Artifact;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;

/**
 * Wraps an `AgentSession` as a `Laravel\Ai` agent — the standalone-chat path
 * from docs/AGENTS_PLAN.md's "Agent turn loop" section. `$instructions` is
 * pre-composed by `Services\Agents\SkillInjector` (base instructions +
 * attached Skills + active AgentKnowledge) and `$tools` by
 * `Services\Agents\ToolRegistry` — both handed in by `AgentRunner`; this
 * class has no opinion on either, only exposes what it's given.
 */
#[MaxSteps(15)]
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
        private readonly ?string $beforeMessageId = null,
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
     * A past user message that carried attachments is replayed with them,
     * so the model can still refer back to a file sent earlier in the
     * conversation.
     *
     * @return iterable<int, Message>
     */
    public function messages(): iterable
    {
        return $this->session->messages()
            ->with('attachments')
            ->when($this->beforeMessageId !== null, fn ($query) => $query->where('id', '!=', $this->beforeMessageId))
            ->oldest()
            ->get()
            ->flatMap(fn (AgentMessage $message): array => match (true) {
                $message->role === AgentMessageRole::Assistant => $this->assistantTurn($message),
                $message->role === AgentMessageRole::User && $message->attachments->isNotEmpty() => [
                    new UserMessage($message->content, $message->attachments->map(fn (Artifact $artifact) => $artifact->toPromptAttachment())),
                ],
                default => [new Message($message->role->value, $message->content)],
            })
            ->all();
    }

    /**
     * Replays an assistant turn the way the SDK's own conversation store
     * does: the tool calls it made and what they returned, then its reply.
     * Without them a later turn can't see data a tool already fetched.
     * Calls with no stored result (turns saved before results were kept)
     * are dropped, since providers reject a call left unanswered.
     *
     * @return array<int, Message>
     */
    private function assistantTurn(AgentMessage $message): array
    {
        $results = collect($message->tool_results ?? [])->keyBy('id');

        $calls = collect($message->tool_calls ?? [])
            ->filter(fn (array $call): bool => $results->has($call['id']))
            ->values();

        $messages = [];

        if ($calls->isNotEmpty()) {
            $messages[] = new AssistantMessage('', $calls->map(ToolCall::fromArray(...)));
            $messages[] = new ToolResultMessage(
                $calls->map(fn (array $call): ToolResult => ToolResult::fromArray($results[$call['id']])),
            );
        }

        if ($calls->isEmpty() || filled($message->content)) {
            $messages[] = new AssistantMessage($message->content);
        }

        return $messages;
    }
}
