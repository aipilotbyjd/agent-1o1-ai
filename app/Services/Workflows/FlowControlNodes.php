<?php

namespace App\Services\Workflows;

use App\Enums\NodeCategory;
use App\Enums\Workflows\FlowControlNodeType;
use App\Nodes\Support\Field;

/**
 * Metadata for the engine-driven node types: Loop, Join Paths, Wait, Human
 * Approval and Sub-Workflow.
 *
 * WorkflowRunner drives these itself instead of calling execute(), so they
 * have no NodeContract class to describe them. This declares them in the same
 * NodeDescriptor shape as every other node.
 */
final class FlowControlNodes
{
    /**
     * The descriptor for one flow-control type.
     */
    public static function descriptor(FlowControlNodeType $type): NodeDescriptor
    {
        return match ($type) {
            FlowControlNodeType::Loop => self::make(
                $type,
                icon: 'repeat',
                name: 'Loop',
                description: 'Runs a child workflow once per item in a list, up to a concurrency limit.',
                configSchema: [
                    'type' => 'object',
                    'required' => ['items_path', 'workflow_id'],
                    'properties' => [
                        'items_path' => Field::text('List to loop over', 'A path to a list in the run, e.g. nodes.fetch.output.rows.', 'nodes.fetch.output.rows'),
                        'workflow_id' => Field::dynamic('Workflow to run per item', 'workspace.workflows', allowCustom: false),
                        'max_concurrent' => Field::integer('Items at once', 'How many items run in parallel.', 1, 1, 50),
                        'on_item_error' => Field::select('If an item fails', ['fail_fast' => 'Stop the loop', 'continue' => 'Skip it and continue', 'collect_errors' => 'Continue and collect errors'], default: 'fail_fast'),
                    ],
                ],
            ),
            FlowControlNodeType::JoinPaths => self::make(
                $type,
                icon: 'git-merge',
                name: 'Join Paths',
                description: 'Waits for every incoming branch to reach it before continuing.',
            ),
            FlowControlNodeType::Wait => self::make(
                $type,
                icon: 'hourglass',
                name: 'Wait',
                description: 'Pauses the run until an external callback resumes it, or until it times out.',
                configSchema: [
                    'type' => 'object',
                    'properties' => [
                        'timeout_seconds' => ['type' => ['integer', 'null'], 'title' => 'Time out after (seconds)', 'description' => 'Leave empty to wait indefinitely.', 'x-widget' => 'number'],
                        'continue_on_timeout' => Field::boolean('Continue on timeout', 'Carry on instead of failing the run when it times out.'),
                    ],
                ],
            ),
            FlowControlNodeType::HumanApproval => self::make(
                $type,
                icon: 'user-check-01',
                name: 'Human Approval',
                description: 'Pauses the run until a workspace member approves or rejects it.',
            ),
            FlowControlNodeType::SubWorkflow => self::make(
                $type,
                icon: 'workflow-square-02',
                name: 'Sub-Workflow',
                description: 'Runs another workflow as a child run and waits for it to complete.',
                configSchema: [
                    'type' => 'object',
                    'required' => ['workflow_id'],
                    'properties' => [
                        'workflow_id' => Field::dynamic('Workflow', 'workspace.workflows', allowCustom: false),
                        'input' => ['type' => ['object', 'null'], 'title' => 'Input', 'description' => 'Passed to the workflow as its run input (JSON).', 'x-widget' => 'json'],
                    ],
                ],
            ),
        };
    }

    /**
     * Build a flow-control descriptor. They all live in the Flow Logic
     * category and declare no output schema.
     *
     * @param  array<string, mixed>  $configSchema  defaults to no settings
     */
    private static function make(
        FlowControlNodeType $type,
        string $icon,
        string $name,
        string $description,
        array $configSchema = ['type' => 'object', 'properties' => []],
    ): NodeDescriptor {
        return new NodeDescriptor(
            type: $type->value,
            category: NodeCategory::FlowLogic->value,
            name: $name,
            description: $description,
            configSchema: $configSchema,
            icon: $icon,
        );
    }
}
