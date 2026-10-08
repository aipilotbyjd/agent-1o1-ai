<?php

namespace App\Services\Workflows;

use App\Enums\Workflows\FlowControlNodeType;
use App\Models\Workflows\WorkflowEdge;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\Engine\GraphAdvancer;

/**
 * Simulates a draft graph without calling anything external — no
 * `NodeContract::execute()` calls, ever. Each node's config is
 * template-resolved (`TemplateResolver`, Stage 5) against a context built
 * from placeholder outputs of the nodes before it in topological order, which
 * catches the authoring mistakes that otherwise only surface at run time: a
 * template pointing at a node that runs later, a misspelled path, wiring
 * that doesn't resolve — all without side effects. Backs
 * `Ai\Tools\WorkflowBuilder\DryRunWorkflowTool`.
 *
 * Each node's simulated output is shaped like its real one when that shape
 * is known — from the node's own pinned data, else from what nodes of its
 * type have produced in the workspace (`NodeOutputShapes`) — so a misspelled
 * field is caught. A reference into a node whose shape is *not* known can't
 * be judged either way; it is reported under `unverified` rather than as a
 * warning, so a first-time graph isn't buried in false alarms.
 */
class DryRunner
{
    public function __construct(
        private readonly GraphValidator $validator,
        private readonly TemplateResolver $templateResolver,
        private readonly NodeOutputShapes $outputShapes,
        private readonly OutputSchemaInferrer $inferrer,
    ) {}

    /**
     * @param  array{nodes?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $graph
     * @param  array<string, mixed>  $input
     * @param  Workspace|null  $workspace  Whose run history supplies output shapes; without one, only pinned data does.
     * @return array<string, mixed>
     */
    public function run(array $graph, array $input = [], ?Workspace $workspace = null): array
    {
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];

        $issues = $this->validator->validate($nodes, $edges);

        // An invalid graph has no meaningful execution order, so simulating
        // it would only produce noise on top of problems the author must
        // fix first — same short-circuit `GraphValidator` itself uses.
        if ($issues !== []) {
            return ['ok' => false, 'issues' => $issues, 'warnings' => [], 'unverified' => [], 'steps' => []];
        }

        $nodesByKey = collect($nodes)->keyBy('key');
        $context = ['input' => $input, 'nodes' => []];
        $unknownShapes = [];
        $warnings = $this->alwaysAndOnErrorWarnings($edges);
        $unverified = [];
        $trace = [];

        foreach ($this->topologicalOrder($nodes, $edges) as $key) {
            $node = $nodesByKey[$key];
            $config = EditorMetadata::strip($node['config'] ?? []);

            foreach ($this->unresolvedPaths($config, $context) as $path) {
                $source = $this->referencedNodeKey($path);

                if ($source !== null && isset($unknownShapes[$source])) {
                    $unverified[] = "Node [{$key}] references [{$path}]; node [{$source}]'s output shape isn't known yet (it hasn't run or been pinned), so the field couldn't be checked.";
                } elseif ($source !== null && isset($context['nodes'][$source])) {
                    $known = implode(', ', array_keys($context['nodes'][$source])) ?: 'none';
                    $warnings[] = "Node [{$key}] references [{$path}], but node [{$source}] isn't known to output that. Known fields: {$known}.";
                } else {
                    $warnings[] = "Node [{$key}] references [{$path}], which nothing provides at that point.";
                }
            }

            $output = $this->sampleOutput($node, $workspace);

            if ($output === null) {
                $unknownShapes[$key] = true;
            }

            $context['nodes'][$key] = $output ?? [];

            $trace[] = [
                'key' => $key,
                'type' => $node['type'],
                'resolved_config' => $this->templateResolver->resolve($config, $context),
                'sample_output' => $output,
            ];
        }

        return [
            'ok' => $warnings === [],
            'issues' => [],
            'warnings' => $warnings,
            'unverified' => $unverified,
            'steps' => $trace,
        ];
    }

    /**
     * Kahn's algorithm, starting from the same entry-node definition
     * `GraphAdvancer` uses at run time (zero incoming *healthy-path* edges)
     * — a `GraphValidator`-clean graph is guaranteed acyclic, so this always
     * terminates having visited every node.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, string>
     */
    private function topologicalOrder(array $nodes, array $edges): array
    {
        $adjacency = [];
        $inDegree = array_fill_keys(array_map(fn (array $node) => $node['key'], $nodes), 0);

        foreach ($edges as $edge) {
            if (($edge['condition'] ?? null) === WorkflowEdge::ERROR_CONDITION) {
                continue;
            }

            $adjacency[$edge['from']][] = $edge['to'];
            $inDegree[$edge['to']] = ($inDegree[$edge['to']] ?? 0) + 1;
        }

        $queue = GraphAdvancer::entryKeys(['nodes' => $nodes, 'edges' => $edges]);
        $visited = [];
        $order = [];

        while ($queue !== []) {
            $key = array_shift($queue);

            if (isset($visited[$key])) {
                continue;
            }

            $visited[$key] = true;
            $order[] = $key;

            foreach ($adjacency[$key] ?? [] as $next) {
                $inDegree[$next]--;

                if ($inDegree[$next] <= 0 && ! isset($visited[$next])) {
                    $queue[] = $next;
                }
            }
        }

        return $order;
    }

    /**
     * A stand-in output shaped like the node's real one, or null when that
     * shape is unknown — `NodeContract` has no `outputSchema()`, so shapes
     * come from observed outputs (see the class docblock), or from the
     * engine for flow-control types. `router`/`filter` always model
     * `result`, since it drives edge routing.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function sampleOutput(array $node, ?Workspace $workspace): ?array
    {
        $pinned = $node['pinned_data'] ?? null;

        $flowControl = FlowControlNodeType::tryFrom($node['type']);

        // A flow-control node's output is fixed by the engine (or, for
        // wait/subflow, set by something outside the graph), so history
        // from other graphs says nothing about it.
        $shaped = match (true) {
            is_array($pinned) && $pinned !== [] => $this->inferrer->placeholder($this->inferrer->infer([$pinned])),
            $flowControl !== null => $flowControl->sampleOutput(),
            $workspace !== null => $this->outputShapes->placeholderFor($workspace, $node['type']),
            default => null,
        };

        if (in_array($node['type'], ['router', 'filter'], true)) {
            return [...($shaped ?? []), 'result' => 'default'];
        }

        return $shaped;
    }

    /**
     * A pair joined both unconditionally and by an `error` edge is almost
     * always a failure path that forgot to drop the original edge: the
     * target then runs on success too, which is valid (so `GraphValidator`
     * can't refuse it) but rarely meant.
     *
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, string>
     */
    private function alwaysAndOnErrorWarnings(array $edges): array
    {
        $conditionsByPair = [];

        foreach ($edges as $edge) {
            $conditionsByPair["{$edge['from']}\0{$edge['to']}"][] = $edge['condition'] ?? null;
        }

        $warnings = [];

        foreach ($conditionsByPair as $pair => $conditions) {
            if (in_array(null, $conditions, true) && in_array(WorkflowEdge::ERROR_CONDITION, $conditions, true)) {
                [$from, $to] = explode("\0", $pair);
                $warnings[] = "Node [{$from}] connects to [{$to}] both always and on error, so [{$to}] runs even when [{$from}] succeeds. Remove the unconditional edge if [{$to}] should only run when [{$from}] fails.";
            }
        }

        return $warnings;
    }

    /**
     * The node a `nodes.<key>...` template path reads from, if it is one.
     */
    private function referencedNodeKey(string $path): ?string
    {
        $segments = explode('.', $path);

        return $segments[0] === 'nodes' && isset($segments[1]) ? $segments[1] : null;
    }

    /**
     * Template paths in the config that the simulated context can't supply.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function unresolvedPaths(array $config, array $context): array
    {
        return array_values(array_filter(
            TemplatePaths::referencedIn($config),
            fn (string $path) => ! $this->addressesSecretStore($path) && data_get($context, $path) === null,
        ));
    }

    /**
     * `{{ secrets.X }}` / `{{ vars.X }}` are resolved from the workspace's
     * secret store at run time (`SecretResolver`), which a dry run has no
     * business reading — the simulated context can't supply them, and
     * warning that "nothing provides" them would be noise on every graph
     * that uses one.
     */
    private function addressesSecretStore(string $path): bool
    {
        return str_starts_with($path, 'secrets.') || str_starts_with($path, 'vars.');
    }
}
