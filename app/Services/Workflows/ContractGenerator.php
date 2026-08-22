<?php

namespace App\Services\Workflows;

use App\Enums\Workflows\InterfaceFieldType;
use App\Models\Workflows\Workflow;

/**
 * Derives a workflow's machine-facing contract: the JSON Schema of the `input`
 * a run accepts, and of the `output` a completed run produces.
 *
 * This is the counterpart to `WorkflowInterface`, not a replacement for it.
 * `WorkflowInterface` describes the *form* a human fills in — labels, help
 * text, select options, defaults. This describes the *payload* a program
 * sends and receives, which is what a Public-API caller, a generated client,
 * and the contract-testing phase (`docs/PLAN.md` Phase 9) each need. The input
 * half is derived from `WorkflowInterface` rather than re-deriving it, so the
 * two can never disagree about which fields exist.
 *
 * The output half is only expressible because `NodeContract::outputSchema()`
 * exists: `runs.output` is `{node_key: that node's output}` for every node
 * that completed (see `GraphAdvancer`), so the contract is exactly the graph's
 * node keys mapped to their declared output shapes.
 */
class ContractGenerator
{
    public function __construct(
        private readonly WorkflowInterface $interface,
        private readonly NodeRegistry $registry,
    ) {}

    /**
     * @return array{
     *     workflow_id: int,
     *     version: int|null,
     *     source: 'published'|'draft',
     *     input: array<string, mixed>,
     *     output: array<string, mixed>,
     * }
     */
    public function generate(Workflow $workflow): array
    {
        // A run is always pinned to the published version, so that is what the
        // contract must describe. The draft is the fallback for a workflow
        // nobody has published yet, whose contract is still worth showing in
        // the editor.
        $version = $workflow->currentVersion;
        $graph = $version?->graph ?? $workflow->draftGraph();

        return [
            'workflow_id' => $workflow->id,
            'version' => $version?->version,
            'source' => $version === null ? 'draft' : 'published',
            'input' => $this->inputSchema($workflow),
            'output' => $this->outputSchema($graph, $workflow->workspace_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inputSchema(Workflow $workflow): array
    {
        $properties = [];
        $required = [];

        foreach ($this->interface->describe($workflow)['fields'] as $field) {
            $type = InterfaceFieldType::tryFrom($field['type']) ?? InterfaceFieldType::String;

            $property = ['type' => $type->jsonSchemaType()];

            if ($field['help'] !== null) {
                $property['description'] = $field['help'];
            }

            if ($type === InterfaceFieldType::Select && $field['options'] !== []) {
                $property['enum'] = array_column($field['options'], 'value');
            }

            if ($field['default'] !== null) {
                $property['default'] = $field['default'];
            }

            $properties[$field['key']] = $property;

            if ($field['required']) {
                $required[] = $field['key'];
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];

        // Omitted rather than empty: `"required": []` and no `required` key
        // mean the same thing to a validator, but the empty array reads as an
        // assertion someone made, and this one nobody did.
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Every node key the run can produce output under, mapped to that node's
     * declared output shape.
     *
     * Nothing is marked `required`: a `Router` sends the run down one branch
     * and the other branch's nodes are skipped, so no individual key is
     * guaranteed to be present in `runs.output`.
     *
     * @param  array{nodes?: array<int, array<string, mixed>>}  $graph
     * @return array<string, mixed>
     */
    private function outputSchema(array $graph, int $workspaceId): array
    {
        $properties = [];

        foreach ($graph['nodes'] ?? [] as $node) {
            $type = $node['type'];
            $config = $node['config'] ?? [];

            // A flow-control type the engine drives itself, or one the
            // registry doesn't know, still writes *something* to its NodeRun —
            // this just can't say what. Same treatment `DryRunner` gives it.
            $properties[$node['key']] = $this->registry->has($type, $workspaceId)
                ? $this->registry->resolve($type, $workspaceId)->outputSchema($config)
                : ['type' => 'object'];
        }

        ksort($properties);

        return ['type' => 'object', 'properties' => $properties];
    }
}
