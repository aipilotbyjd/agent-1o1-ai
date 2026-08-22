<?php

namespace App\Services\Workflows;

use App\Models\Workflows\WorkflowEdge;

/**
 * Runs at both draft-save time (`Workflow::replaceGraph()`, config-schema
 * issues only, via `ConfigSchemaValidator` directly) and publish time
 * (`Workflow::publishVersion()`, the full sequence below) — see
 * docs/WORKFLOWS_PLAN.md's "Validation" section. Checks run in order, later
 * ones skipped once an earlier one fails, since a dangling-edge graph makes
 * cycle/reachability checks meaningless.
 *
 * @phpstan-type NodeShape array{key: string, type: string, config: array<string, mixed>}
 * @phpstan-type EdgeShape array{from: string, to: string, condition: string|null}
 */
class GraphValidator
{
    public function __construct(
        private readonly NodeRegistry $registry,
        private readonly ConfigSchemaValidator $configValidator,
    ) {}

    /**
     * `$workspaceId` is only consulted for `custom:{id}` node types, whose
     * config schema lives on a workspace-scoped `CustomNode` row rather than
     * in the built-in registry. Callers that can't name a workspace still get
     * every other check; custom nodes just go unchecked for them, exactly as
     * an unknown type does.
     *
     * @param  array<int, array{key: string, type: string, config: array<string, mixed>}>  $nodes
     * @param  array<int, array{from: string, to: string, condition: string|null}>  $edges
     * @return array<int, string>
     */
    public function validate(array $nodes, array $edges, ?int $workspaceId = null): array
    {
        if (($errors = $this->duplicateKeyErrors($nodes)) !== []) {
            return $errors;
        }

        $keys = array_map(fn (array $node) => $node['key'], $nodes);

        if (($errors = $this->danglingEdgeErrors($keys, $edges)) !== []) {
            return $errors;
        }

        $adjacency = $this->buildAdjacency($edges);

        if (($errors = $this->cycleErrors($keys, $adjacency)) !== []) {
            return $errors;
        }

        $entryKeys = $this->entryNodeKeys($keys, $adjacency);

        if ($entryKeys === []) {
            return ['The graph has no entry node — every node has an incoming edge.'];
        }

        if (($errors = $this->unreachableNodeErrors($keys, $entryKeys, $adjacency)) !== []) {
            return $errors;
        }

        return $this->nodeConfigErrors($nodes, $workspaceId);
    }

    /**
     * @param  array<int, array{key: string, type: string, config: array<string, mixed>}>  $nodes
     * @return array<int, string>
     */
    private function duplicateKeyErrors(array $nodes): array
    {
        $seen = [];
        $errors = [];

        foreach ($nodes as $node) {
            if (isset($seen[$node['key']])) {
                $errors[] = "Duplicate node key '{$node['key']}'.";

                continue;
            }

            $seen[$node['key']] = true;
        }

        return $errors;
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<int, array{from: string, to: string, condition: string|null}>  $edges
     * @return array<int, string>
     */
    private function danglingEdgeErrors(array $keys, array $edges): array
    {
        $known = array_flip($keys);
        $errors = [];

        foreach ($edges as $edge) {
            if (! isset($known[$edge['from']])) {
                $errors[] = "Edge references unknown source node '{$edge['from']}'.";
            }

            if (! isset($known[$edge['to']])) {
                $errors[] = "Edge references unknown target node '{$edge['to']}'.";
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array{from: string, to: string, condition: string|null}>  $edges
     * @return array<string, array<int, string>>
     */
    private function buildAdjacency(array $edges): array
    {
        $adjacency = [];

        foreach ($edges as $edge) {
            // A failure-only edge never advances a graph traversal used for
            // cycle detection or reachability — it only fires when the
            // source has already failed, which is not a path a healthy run
            // takes.
            if ($edge['condition'] === WorkflowEdge::ERROR_CONDITION) {
                continue;
            }

            $adjacency[$edge['from']][] = $edge['to'];
        }

        return $adjacency;
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<string, array<int, string>>  $adjacency
     * @return array<int, string>
     */
    private function cycleErrors(array $keys, array $adjacency): array
    {
        $state = array_fill_keys($keys, 'unvisited');

        foreach ($keys as $key) {
            if ($state[$key] === 'unvisited' && $this->hasCycleFrom($key, $adjacency, $state)) {
                return ['The graph contains a cycle.'];
            }
        }

        return [];
    }

    /**
     * @param  array<string, array<int, string>>  $adjacency
     * @param  array<string, string>  $state
     */
    private function hasCycleFrom(string $key, array $adjacency, array &$state): bool
    {
        $state[$key] = 'visiting';

        foreach ($adjacency[$key] ?? [] as $next) {
            if ($state[$next] === 'visiting') {
                return true;
            }

            if ($state[$next] === 'unvisited' && $this->hasCycleFrom($next, $adjacency, $state)) {
                return true;
            }
        }

        $state[$key] = 'visited';

        return false;
    }

    /**
     * An entry node is one with no incoming *healthy-path* edge — an
     * error-only edge doesn't disqualify a node from being an entry point,
     * since it's only reachable when something upstream has already failed.
     *
     * @param  array<int, string>  $keys
     * @param  array<string, array<int, string>>  $adjacency
     * @return array<int, string>
     */
    private function entryNodeKeys(array $keys, array $adjacency): array
    {
        $hasIncoming = array_fill_keys(array_merge(...array_values($adjacency ?: [[]])), true);

        return array_values(array_filter($keys, fn (string $key) => ! isset($hasIncoming[$key])));
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $entryKeys
     * @param  array<string, array<int, string>>  $adjacency
     * @return array<int, string>
     */
    private function unreachableNodeErrors(array $keys, array $entryKeys, array $adjacency): array
    {
        $visited = array_fill_keys($entryKeys, true);
        $queue = $entryKeys;

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($adjacency[$current] ?? [] as $next) {
                if (! isset($visited[$next])) {
                    $visited[$next] = true;
                    $queue[] = $next;
                }
            }
        }

        $unreachable = array_values(array_filter($keys, fn (string $key) => ! isset($visited[$key])));

        if ($unreachable === []) {
            return [];
        }

        return array_map(fn (string $key) => "Node '{$key}' is unreachable from any entry node.", $unreachable);
    }

    /**
     * @param  array<int, array{key: string, type: string, config: array<string, mixed>}>  $nodes
     * @return array<int, string>
     */
    private function nodeConfigErrors(array $nodes, ?int $workspaceId): array
    {
        $errors = [];

        foreach ($nodes as $node) {
            // Flow-control types (loop, subflow, human_approval, wait, ...)
            // and trigger types aren't in NodeRegistry yet — see
            // docs/WORKFLOWS_AGENTS_BUILD_PLAN.md Stage 4. Nothing to
            // schema-check for those until they exist; this is not a
            // silent pass for genuinely unknown types once every stage
            // lands.
            if (! $this->registry->has($node['type'], $workspaceId)) {
                continue;
            }

            $schema = $this->registry->resolve($node['type'], $workspaceId)->configSchema();

            foreach ($this->configValidator->validate($schema, $node['config']) as $error) {
                $errors[] = "Node '{$node['key']}': {$error}";
            }
        }

        return $errors;
    }
}
