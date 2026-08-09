<?php

namespace App\Enums\Workflows;

/**
 * Node types the engine drives directly (graph traversal / pause-resume),
 * rather than resolving through `NodeRegistry`/`NodeContract::execute()` —
 * see docs/WORKFLOWS_PLAN.md's "Node contract & registry" section.
 * `WorkflowRunner::executeStep()` branches on this before falling through to
 * the normal `NodeContract` path.
 */
enum FlowControlNodeType: string
{
    case HumanApproval = 'human_approval';
    case Wait = 'wait';
    case SubWorkflow = 'subflow';
    case Loop = 'loop';
    case JoinPaths = 'join_paths';
}
