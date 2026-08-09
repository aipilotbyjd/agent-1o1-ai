<?php

namespace App\Services\Workflows;

use App\Models\Workflows\WorkflowEdge;
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
 */
class DryRunner
{
    public function __construct(
        private readonly GraphValidator $validator,
        private readonly TemplateResolver $templateResolver,
    ) {}

    /**
     * @param  array{nodes?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $graph
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function run(array $graph, array $input = []): array
    {
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];

        $issues = $this->validator->validate($nodes, $edges);

        // An invalid graph has no meaningful execution order, so simulating
        // it would only produce noise on top of problems the author must
        // fix first — same short-circuit `GraphValidator` itself uses.
        if ($issues !== []) {
            return ['ok' => false, 'issues' => $issues, 'warnings' => [], 'steps' => []];
        }

        $nodesByKey = collect($nodes)->keyBy('key');
        $context = ['input' => $input, 'nodes' => []];
        $warnings = [];
        $trace = [];

        foreach ($this->topologicalOrder($nodes, $edges) as $key) {
            $node = $nodesByKey[$key];
            $config = $node['config'] ?? [];

            foreach ($this->unresolvedPaths($config, $context) as $path) {
                $warnings[] = "Node [{$key}] references [{$path}], which nothing provides at that point.";
            }

            $output = $this->sampleOutput($node['type']);
            $context['nodes'][$key] = $output;

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
     * A placeholder output — `NodeContract` has no `outputSchema()` to
     * synthesize a richer sample from (a deliberate scope cut; see
     * docs/WORKFLOWS_AGENTS_BUILD_PLAN.md's WorkflowBuilderAgent plan).
     * `router`/`filter` are special-cased since their `result` output
     * drives edge routing and is worth modelling explicitly.
     *
     * @return array<string, mixed>
     */
    private function sampleOutput(string $type): array
    {
        return in_array($type, ['router', 'filter'], true) ? ['result' => 'default'] : [];
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
            fn (string $path) => data_get($context, $path) === null,
        ));
    }
}
