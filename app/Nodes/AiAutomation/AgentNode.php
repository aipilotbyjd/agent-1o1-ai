<?php

namespace App\Nodes\AiAutomation;

use App\Contracts\NodeContract;
use App\Enums\NodeCategory;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Services\Agents\AgentRunner;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Embeds an `Agent` inside a workflow — the Workflow→Agent direction (Stage 7's
 * `WorkflowTool` is the reverse, Agent→Workflow). Ported design from the old
 * project's `AgentStepHandler`: resolves the `Agent` scoped to the run's
 * workspace, prompts it synchronously via `AgentRunner::ask()`, and returns
 * `{text, usage}` — `usage` lands on `NodeRun.usage` the same way
 * `AskAiNode`'s does (`WorkflowRunner::executeNodeContract()`), which is all
 * `CreditMeter` needs; no separate credit-accounting wiring for this node.
 *
 * `config.prompt` is already `{{ }}`-resolved by the time `execute()` sees it
 * (`WorkflowRunner`'s templating pass runs before any node type) — omitting
 * it falls back to `input.message` from the run's own input, mirroring the
 * old project's default.
 */
class AgentNode implements NodeContract
{
    public function __construct(private readonly AgentRunner $runner) {}

    public function type(): string
    {
        return 'agent';
    }

    public function category(): string
    {
        return NodeCategory::AiAutomation->value;
    }

    public function name(): string
    {
        return 'Agent';
    }

    public function description(): string
    {
        return 'Prompts one of this workspace\'s Agents (with its instructions, skills, and tools) and returns its reply.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['agent_id'],
            'properties' => [
                'agent_id' => ['type' => 'integer'],
                'prompt' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $agent = Agent::query()
            ->where('id', $config['agent_id'] ?? null)
            ->where('workspace_id', $run->workspace_id)
            ->first();

        if ($agent === null) {
            throw new InvalidArgumentException("Agent [{$config['agent_id']}] does not exist in this workspace.");
        }

        $prompt = $config['prompt'] ?? (string) Arr::get($context, 'input.message', '');

        return $this->runner->ask($agent, $run, $prompt);
    }
}
