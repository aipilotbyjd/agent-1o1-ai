<?php

namespace App\Enums\Billing;

/**
 * `credit_transactions.source_type` — what caused the charge.
 */
enum CreditTransactionType: string
{
    case NodeRun = 'node_run';
    case AgentStep = 'agent_step';
}
