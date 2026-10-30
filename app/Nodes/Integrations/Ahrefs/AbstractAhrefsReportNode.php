<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Models\Runs\Run;
use InvalidArgumentException;

/**
 * A node that makes one Ahrefs call, described by `endpoint()`. Settings carry the
 * names Ahrefs gives its parameters and are sent as they are. `limit` falls back to
 * 100, as Ahrefs charges per row and would otherwise return 1,000, and a report that
 * must have a `date` uses today.
 *
 * @phpstan-type Endpoint array{method: string, path: string, items: string, select?: string, query?: list<string>, body?: list<string>, json?: list<string>, lists?: list<string>, integers?: list<string>, dated?: bool, limited?: bool}
 */
abstract class AbstractAhrefsReportNode extends AbstractAhrefsNode
{
    /**
     * The call this node makes: its verb, path and the key holding the answer; the
     * default columns; which settings go in the query and which in the body, and how
     * those are read (`json`: an object or list, `lists`: several values, `integers`).
     *
     * @return Endpoint
     */
    abstract protected function endpoint(): array;

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shape($this->read($run, $config), $this->endpoint()['items']);
    }

    /**
     * The call, with the node's settings in place.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function read(Run $run, array $config): array
    {
        $endpoint = $this->endpoint();
        $query = [];

        foreach ($endpoint['query'] ?? [] as $parameter) {
            $query[$parameter] = $this->setting($config, $parameter, $endpoint);
        }

        $body = null;

        if (isset($endpoint['body'])) {
            $body = [];

            foreach ($endpoint['body'] as $parameter) {
                $value = $this->setting($config, $parameter, $endpoint);

                if ($value !== null && $value !== '' && $value !== []) {
                    $body[$parameter] = $value;
                }
            }
        }

        return $this->ahrefs($run, $config, $endpoint['method'], $endpoint['path'], $query, $body);
    }

    /**
     * One setting as Ahrefs wants it.
     *
     * @param  array<string, mixed>  $config
     * @param  Endpoint  $endpoint
     *
     * @throws InvalidArgumentException
     */
    private function setting(array $config, string $parameter, array $endpoint): mixed
    {
        $value = $config[$parameter] ?? null;

        if (blank($value) && $value !== false && $value !== 0) {
            $value = match (true) {
                $parameter === 'select' => $endpoint['select'] ?? null,
                $parameter === 'limit' && ($endpoint['limited'] ?? false) => 100,
                $parameter === 'date' && ($endpoint['dated'] ?? false) => now()->utc()->toDateString(),
                default => null,
            };
        }

        if ($value === null) {
            return null;
        }

        if (in_array($parameter, $endpoint['lists'] ?? [], true)) {
            $items = is_string($value) ? (preg_split('/[,\n]/', $value) ?: []) : (array) $value;
            $value = array_values(array_filter(array_map(fn (mixed $item): string => trim((string) $item), $items), fn (string $item): bool => $item !== ''));

            return in_array($parameter, $endpoint['query'] ?? [], true) ? implode(',', $value) : $value;
        }

        if (in_array($parameter, $endpoint['json'] ?? [], true) && is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : throw new InvalidArgumentException("The {$parameter} setting must be valid JSON.");
        }

        if (in_array($parameter, $endpoint['integers'] ?? [], true) && is_numeric($value)) {
            return (int) $value;
        }

        return $value;
    }
}
