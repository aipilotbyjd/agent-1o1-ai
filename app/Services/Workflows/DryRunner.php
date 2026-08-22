<?php

namespace App\Services\Workflows;

use App\Models\Workflows\WorkflowEdge;
use App\Services\Workflows\Engine\GraphAdvancer;

/**
 * Simulates a draft graph without calling anything external — no
 * `NodeContract::execute()` calls, ever. Each node's config is
 * template-resolved (`TemplateResolver`, Stage 5) against a context built
 * from sample outputs — synthesized by `SchemaSampler` from each node's
 * `NodeContract::outputSchema()` — of the nodes before it in topological order, which
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
        private readonly NodeRegistry $registry,
        private readonly SchemaSampler $sampler,
    ) {}

    /**
     * `$workspaceId` is only needed to resolve `custom:{id}` node types,
     * whose schemas live on a workspace-scoped `CustomNode` row — see
     * `NodeRegistry::has()`. Without it a custom node simulates as a
     * free-form object, the same as any type the registry doesn't know.
     *
     * @param  array{nodes?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $graph
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function run(array $graph, array $input = [], ?int $workspaceId = null): array
    {
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];

        $issues = $this->validator->validate($nodes, $edges, $workspaceId);

        // An invalid graph has no meaningful execution order, so simulating
        // it would only produce noise on top of problems the author must
        // fix first — same short-circuit `GraphValidator` itself uses.
        if ($issues !== []) {
            return ['ok' => false, 'issues' => $issues, 'warnings' => [], 'steps' => []];
        }

        $nodesByKey = collect($nodes)->keyBy('key');
        $context = ['input' => $input, 'nodes' => []];
        $openPaths = [];
        $warnings = [];
        $trace = [];

        foreach ($this->topologicalOrder($nodes, $edges) as $key) {
            $node = $nodesByKey[$key];
            $config = $node['config'] ?? [];

            foreach ($this->unresolvedPaths($config, $context, $openPaths) as $path) {
                $warnings[] = "Node [{$key}] references [{$path}], which nothing provides at that point.";
            }

            $schema = $this->outputSchema($node['type'], $config, $workspaceId);
            $output = $this->sampler->sample($schema);
            $output = is_array($output) ? $output : [];
            $context['nodes'][$key] = $output;
            $openPaths = [...$openPaths, ...$this->sampler->openPaths($schema, "nodes.{$key}")];

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
     * The node's declared output shape, which `SchemaSampler` turns into the
     * sample the nodes after it see.
     *
     * A type the registry can't resolve — a flow-control type the engine
     * drives itself (`merge`, `wait`, ...), or a genuinely unknown one — has
     * no schema to ask for. It gets a free-form object, which is what those
     * types are from a simulation's point of view: they do produce output at
     * run time, this simulation just can't say what. Warning on references
     * into them would be noise, not a finding.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function outputSchema(string $type, array $config, ?int $workspaceId): array
    {
        if (! $this->registry->has($type, $workspaceId)) {
            return ['type' => 'object'];
        }

        return $this->registry->resolve($type, $workspaceId)->outputSchema($config);
    }

    /**
     * Template paths in the config that the simulated context can't supply.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $openPaths
     * @return array<int, string>
     */
    private function unresolvedPaths(array $config, array $context, array $openPaths): array
    {
        return array_values(array_filter(
            TemplatePaths::referencedIn($config),
            fn (string $path) => ! $this->addressesSecretStore($path)
                && ! $this->isUnderOpenSchema($path, $openPaths)
                && data_get($context, $path) === null,
        ));
    }

    /**
     * Whether an upstream node's schema stops describing the value at or
     * above this path — `{{ nodes.a.body.items.0.id }}` against
     * `CallApiNode`'s unconstrained `body`, say. Such a path is unknowable,
     * not absent, so it isn't a warning.
     *
     * @param  array<int, string>  $openPaths
     */
    private function isUnderOpenSchema(string $path, array $openPaths): bool
    {
        $segments = explode('.', $path);

        foreach ($openPaths as $openPath) {
            $openSegments = explode('.', $openPath);

            if (count($openSegments) > count($segments)) {
                continue;
            }

            foreach ($openSegments as $index => $segment) {
                // `*` stands in for a list index the schema described only by
                // its `items` — see `SchemaSampler::openPaths()`.
                if ($segment !== '*' && $segment !== $segments[$index]) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
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
