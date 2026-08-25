<?php

namespace App\Enums\Billing;

/**
 * `credit_transactions.source_type` — what caused the charge.
 */
enum CreditTransactionType: string
{
    case NodeRun = 'node_run';
    case AgentStep = 'agent_step';

    /**
     * One graded case of an agent eval suite. Kept distinct from `AgentStep`
     * so eval spend can be told apart from production traffic on the ledger —
     * and because idempotency is per `(source_type, source_id)`, and an
     * `AgentEvalCaseResult` id would otherwise collide with an
     * `AgentMessage` id.
     */
    case EvalCase = 'eval_case';

    /**
     * One automatic QA grading of a live `AgentSession` — see
     * `Services\Agents\SessionEvaluator`. Kept distinct from `AgentStep` for
     * the same ledger-clarity and idempotency-key reasons as `EvalCase`.
     */
    case SessionEvaluation = 'session_evaluation';
}
