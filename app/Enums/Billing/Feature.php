<?php

namespace App\Enums\Billing;

/**
 * Keys looked up against `Plan.features` (a plain `[key => bool]` JSON map)
 * via `Plan::hasFeature()`.
 */
enum Feature: string
{
    case CreditPacks = 'credit_packs';
    case GitSync = 'git_sync';
    case WorkflowApprovals = 'workflow_approvals';
    case CustomNodes = 'custom_nodes';
    case PrioritySupport = 'priority_support';
}
