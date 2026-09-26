<?php

namespace App\Ai\Tools;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Services\Agents\SkillInjector;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lets an agent with `allow_self_updates` rewrite its own instructions when
 * the user corrects it or states a lasting preference. Saving goes through
 * `AgentObserver`, so every change is a new `AgentVersion` that can be rolled
 * back. The agent is reloaded rather than saved from the turn's copy, which
 * is a pinned-version snapshot whose other attributes aren't the live ones.
 *
 * The model sees its full prompt, which also carries the skills, knowledge
 * and memories `SkillInjector` adds. Models copy those back despite being
 * told not to, so any injected section is stripped before saving; otherwise
 * it would be duplicated, and outlive the skill being detached.
 *
 * The conversation the correction was made in is moved onto the new
 * version, so the change applies from the agent's next reply there rather
 * than only in later conversations.
 */
class UpdateInstructionsTool implements Tool
{
    private ?Agent $liveAgent = null;

    public function __construct(
        private readonly Agent $agent,
        private readonly SkillInjector $skillInjector,
        private readonly ?string $userId = null,
        private readonly ?AgentSession $session = null,
    ) {}

    public function name(): string
    {
        return 'update_own_instructions';
    }

    public function description(): Stringable|string
    {
        return 'Permanently updates your own base instructions. Use it only when the user corrects you or tells you a rule, '
            .'preference or fact that should apply to all future conversations, not for one-off requests. '
            .'Pass the COMPLETE revised base instructions: they replace the current ones, so keep everything that still applies. '
            .'Do not include "## Skills", "## Knowledge:" or memory sections; those are managed separately. '
            ."Your current base instructions are:\n\n".($this->liveAgent()->instructions ?? '(none yet)');
    }

    public function handle(Request $request): Stringable|string
    {
        $agent = $this->liveAgent();

        $instructions = str_replace(
            $this->skillInjector->injectedSections($agent, $this->userId),
            '',
            (string) $request['instructions'],
        );
        $instructions = trim((string) preg_replace("/\n{3,}/", "\n\n", $instructions));

        if ($instructions === '') {
            return 'Not updated: the instructions were empty.';
        }

        $agent->update(['instructions' => $instructions]);

        $this->session?->update(['agent_version_id' => $agent->versions()->latest('version')->value('id')]);

        return 'Instructions updated. The change applies from your next reply.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'instructions' => $schema->string()->description('The complete revised base instructions.')->required(),
        ];
    }

    private function liveAgent(): Agent
    {
        return $this->liveAgent ??= Agent::query()->findOrFail($this->agent->id);
    }
}
