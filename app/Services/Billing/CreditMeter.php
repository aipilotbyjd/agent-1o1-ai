<?php

namespace App\Services\Billing;

use App\Models\Agents\AgentEvalCaseResult;
use App\Models\Agents\AgentMessage;
use App\Models\Runs\NodeRun;

/**
 * The per-node/per-agent-step cost table docs/PLAN.md's "Architecture
 * Overview" describes: "1 base credit/run, AI nodes billed by tokens, some
 * integration nodes have fixed costs". Only the base-credit + token-based
 * pieces are implemented here — per-node fixed-cost overrides and BYOK's
 * "halve AI credit cost" land alongside real Connector credentials
 * (docs/PLAN.md Phase 6/7).
 */
class CreditMeter
{
    /**
     * Every node run costs at least this much just for having executed.
     */
    private const int BASE_CREDITS_PER_RUN = 1;

    /**
     * Additional credits per 1,000 prompt+completion tokens, rounded up.
     */
    private const int TOKENS_PER_CREDIT = 1000;

    public function costForNodeRun(NodeRun $nodeRun): int
    {
        return self::BASE_CREDITS_PER_RUN + $this->tokenCost($nodeRun->usage);
    }

    public function costForAgentMessage(AgentMessage $message): int
    {
        return self::BASE_CREDITS_PER_RUN + $this->tokenCost($message->usage);
    }

    /**
     * One graded eval case. Priced identically to a production turn, because
     * it is one — the same agent, the same model, the same tokens.
     */
    public function costForEvalCase(AgentEvalCaseResult $result): int
    {
        return self::BASE_CREDITS_PER_RUN + $this->tokenCost($result->usage);
    }

    /**
     * @param  array<string, mixed>|null  $usage
     */
    private function tokenCost(?array $usage): int
    {
        if ($usage === null) {
            return 0;
        }

        $totalTokens = (int) ($usage['prompt_tokens'] ?? 0) + (int) ($usage['completion_tokens'] ?? 0);

        return (int) ceil($totalTokens / self::TOKENS_PER_CREDIT);
    }
}
