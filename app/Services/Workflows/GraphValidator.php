<?php

namespace App\Services\Workflows;

use App\Enums\Triggers\TriggerNodeType;
use App\Models\Workflows\WorkflowEdge;
use App\Rules\SafeOutboundUrl;
use App\Services\Triggers\TriggerNodes;
use Cron\CronExpression;
use Illuminate\Support\Facades\Validator;

/**
 * Runs at both draft-save time (`Workflow::replaceGraph()`, config-schema
 * issues only, via `ConfigSchemaValidator` directly) and publish time
 * (`Workflow::publishVersion()`, the full sequence below): see
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
     * @param  array<int, array{key: string, type: string, config: array<string, mixed>}>  $nodes
     * @param  array<int, array{from: string, to: string, condition: string|null}>  $edges
     * @return array<int, string>
     */
    public function validate(array $nodes, array $edges): array
    {
        return self::texts($this->issues($nodes, $edges));
    }

    /**
     * The same checks as `validate()`, each pointed at the node (and config
     * field) it's about, with a message for the person building the workflow.
     *
     * @param  array<int, array{key: string, type: string, config: array<string, mixed>}>  $nodes
     * @param  array<int, array{from: string, to: string, condition: string|null}>  $edges
     * @return array<int, ValidationIssue>
     */
    public function issues(array $nodes, array $edges): array
    {
        if ($nodes === []) {
            return [new ValidationIssue('Add at least one step before publishing.', detail: 'The workflow has no nodes. Add at least one node before publishing.')];
        }

        if (($issues = $this->structuralIssues($nodes, $edges)) !== []) {
            return $issues;
        }

        $keys = array_map(fn (array $node) => $node['key'], $nodes);

        $adjacency = $this->buildAdjacency($edges);

        if (($issues = $this->cycleErrors($keys, $adjacency)) !== []) {
            return $issues;
        }

        $entryKeys = $this->entryNodeKeys($keys, $adjacency);

        if ($entryKeys === []) {
            return [new ValidationIssue(
                'Every step has a connection coming into it, so the workflow has nowhere to start.',
                detail: 'The graph has no entry node; every node has an incoming edge.',
            )];
        }

        if (($issues = $this->unreachableNodeErrors($keys, $entryKeys, $adjacency)) !== []) {
            return $issues;
        }

        return [...$this->unknownTypeErrors($nodes), ...$this->nodeConfigErrors($nodes), ...$this->triggerConfigErrors($nodes), ...WorkflowIo::issues($nodes)];
    }

    /**
     * What a trigger node's schema can't express: publishing materializes
     * it into a `triggers` row (`TriggerNodeSync`) that the scheduler parses
     * every minute and the poller fetches server-side, so the same checks
     * `StoreTriggerRequest` applies to a hand-made trigger apply here.
     *
     * @param  array<int, array{key: string, type: string, config: array<string, mixed>}>  $nodes
     * @return array<int, ValidationIssue>
     */
    private function triggerConfigErrors(array $nodes): array
    {
        $errors = [];

        foreach ($nodes as $node) {
            $config = $node['config'] ?? [];

            switch (TriggerNodeType::tryFrom((string) $node['type'])) {
                case TriggerNodeType::Schedule:
                    if (filled($config['cron'] ?? null) && ! CronExpression::isValidExpression((string) $config['cron'])) {
                        $errors[] = new ValidationIssue('This schedule is not a valid cron expression.', $node['key'], 'cron');
                    }

                    if (filled($config['timezone'] ?? null) && ! in_array($config['timezone'], timezone_identifiers_list(), true)) {
                        $errors[] = new ValidationIssue('Pick a timezone from the list.', $node['key'], 'timezone');
                    }

                    break;
                case TriggerNodeType::Webhook:
                    if (filled($config['header_name'] ?? null) && ! preg_match('/^[A-Za-z0-9-]{1,64}$/', (string) $config['header_name'])) {
                        $errors[] = new ValidationIssue('A header name can only use letters, numbers and dashes.', $node['key'], 'header_name');
                    }

                    break;
                case TriggerNodeType::Polling:
                    if (filled($config['url'] ?? null) && Validator::make(['url' => $config['url']], ['url' => [new SafeOutboundUrl]])->fails()) {
                        $errors[] = new ValidationIssue('The polling URL must be a public http(s) address.', $node['key'], 'url');
                    }

                    break;
                default:
                    break;
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, ValidationIssue>  $issues
     * @return array<int, string>
     */
    public static function texts(array $issues): array
    {
        return array_map(fn (ValidationIssue $issue): string => $issue->text(), $issues);
    }

    /**
     * The problems that make a graph impossible to even *store*: repeated
     * node keys and edges to nodes that aren't there both break
     * `workflow_nodes`/`workflow_edges` constraints. Every save path
     * (`Workflow::replaceGraph()`, `WorkflowBuilderSession::replaceDraft()`)
     * checks these even though a draft may otherwise be mid-edit, so a bad
     * payload is a 422 naming the problem rather than a constraint-violation
     * 500. A repeated edge is not an error: see `uniqueEdges()`.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, string>
     */
    public function structuralErrors(array $nodes, array $edges): array
    {
        return self::texts($this->structuralIssues($nodes, $edges));
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, ValidationIssue>
     */
    public function structuralIssues(array $nodes, array $edges): array
    {
        if (($issues = $this->duplicateKeyErrors($nodes)) !== []) {
            return $issues;
        }

        $keys = array_map(fn (array $node) => $node['key'], $nodes);

        return [...$this->danglingEdgeErrors($keys, $edges), ...$this->triggerInboundErrors($nodes, $edges)];
    }

    /**
     * A trigger is where a run starts, so nothing can lead into it.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, ValidationIssue>
     */
    private function triggerInboundErrors(array $nodes, array $edges): array
    {
        $triggerKeys = [];
        $triggerNodes = app(TriggerNodes::class);

        foreach ($nodes as $node) {
            if ($triggerNodes->isTriggerEntry($node)) {
                $triggerKeys[$node['key']] = true;
            }
        }

        $errors = [];

        foreach (array_unique(array_column($edges, 'to')) as $to) {
            if (isset($triggerKeys[$to])) {
                $errors[] = new ValidationIssue('A trigger must be the first step: remove the connection leading into it.', $to, detail: "Trigger node '{$to}' has an incoming edge.");
            }
        }

        return $errors;
    }

    /**
     * The edges with exact repeats (same `from`, `to` and `condition`)
     * dropped. A repeat changes nothing about how a run moves (a node is
     * only ever started once per run), and it sits exactly on top of its
     * twin on the canvas where nobody can see it to delete it, so saves
     * collapse repeats rather than refuse them. They also used to slip past
     * the `workflow_edges` unique index, which treats NULL conditions as
     * distinct.
     *
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, array<string, mixed>>
     */
    public static function uniqueEdges(array $edges): array
    {
        $seen = [];

        return array_values(array_filter($edges, function (array $edge) use (&$seen): bool {
            $identity = json_encode([$edge['from'], $edge['to'], $edge['condition'] ?? null]);

            if (isset($seen[$identity])) {
                return false;
            }

            return $seen[$identity] = true;
        }));
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, ValidationIssue>
     */
    private function duplicateKeyErrors(array $nodes): array
    {
        $seen = [];
        $errors = [];

        foreach ($nodes as $node) {
            if (isset($seen[$node['key']])) {
                $errors[] = new ValidationIssue("Two steps share the key '{$node['key']}'.", $node['key'], detail: "Duplicate node key '{$node['key']}'.");

                continue;
            }

            $seen[$node['key']] = true;
        }

        return $errors;
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, ValidationIssue>
     */
    private function danglingEdgeErrors(array $keys, array $edges): array
    {
        $known = array_flip($keys);
        $errors = [];

        foreach ($edges as $edge) {
            if (! isset($known[$edge['from']])) {
                $errors[] = new ValidationIssue('A connection starts from a step that no longer exists.', detail: "Edge references unknown source node '{$edge['from']}'.");
            }

            if (! isset($known[$edge['to']])) {
                $errors[] = new ValidationIssue('A connection points to a step that no longer exists.', detail: "Edge references unknown target node '{$edge['to']}'.");
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
            // cycle detection or reachability: it only fires when the
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
     * @return array<int, ValidationIssue>
     */
    private function cycleErrors(array $keys, array $adjacency): array
    {
        $state = array_fill_keys($keys, 'unvisited');

        foreach ($keys as $key) {
            if ($state[$key] === 'unvisited' && $this->hasCycleFrom($key, $adjacency, $state)) {
                return [new ValidationIssue('The workflow loops back on itself. Remove the connection that closes the loop, or use a Loop step.', detail: 'The graph contains a cycle.')];
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
     * An entry node is one with no incoming *healthy-path* edge: an
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
     * @return array<int, ValidationIssue>
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

        return array_map(fn (string $key) => new ValidationIssue(
            "This step isn't connected to the start of the workflow, so it would never run.",
            $key,
            detail: "Node '{$key}' is unreachable from any entry node.",
        ), $unreachable);
    }

    /**
     * Nodes whose type doesn't exist at all: a typo, or a node type that
     * was since removed. `Workflow::replaceGraph()` lets a draft hold one
     * (it only checks what it has a schema for); publishing must not, or
     * every run fails at that node. `custom:` nodes are the exception: they
     * publish by design and fail only at run time (see `NodeRegistry::isCustom()`).
     *
     * @param  array<int, array{key: string, type: string, config: array<string, mixed>}>  $nodes
     * @return array<int, ValidationIssue>
     */
    private function unknownTypeErrors(array $nodes): array
    {
        $errors = [];

        foreach ($nodes as $node) {
            if (! $this->registry->isPlaceable($node['type']) && ! $this->registry->isCustom($node['type'])) {
                $errors[] = new ValidationIssue(
                    "This step's type no longer exists. Replace or remove it.",
                    $node['key'],
                    detail: "Node '{$node['key']}': there is no node type [{$node['type']}].",
                );
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array{key: string, type: string, config: array<string, mixed>}>  $nodes
     * @return array<int, ValidationIssue>
     */
    private function nodeConfigErrors(array $nodes): array
    {
        $errors = [];

        foreach ($nodes as $node) {
            // Parsed nowhere that could act on it: a running node can't be
            // interrupted, so promising a per-step timeout would be a lie.
            if (isset($node['config']['_options']['timeout_seconds'])) {
                $errors[] = new ValidationIssue(
                    "Per-step timeouts aren't supported: a running step can't be interrupted.",
                    $node['key'],
                    detail: "Node '{$node['key']}': _options.timeout_seconds is not supported; a running node cannot be interrupted.",
                );
            }

            // Flow-control types (loop, subflow, human_approval, wait, ...)
            // are checked against their catalog schemas like any other
            // node. Types with no schema at all (`custom:` nodes and
            // unknown types (reported by `unknownTypeErrors()`)) are skipped.
            $schema = $this->registry->configSchemaFor($node['type']);

            if ($schema === null) {
                continue;
            }

            foreach ($this->configValidator->issues($schema, $node['config']) as $issue) {
                $errors[] = ValidationIssue::forNodeConfig($node['key'], $issue);
            }
        }

        return $errors;
    }
}
