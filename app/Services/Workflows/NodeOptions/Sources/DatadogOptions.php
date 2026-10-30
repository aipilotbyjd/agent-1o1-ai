<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Nodes\Integrations\Datadog\AbstractDatadogNode;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\ConnectionException;
use InvalidArgumentException;

/**
 * Monitors, dashboards, SLOs, Synthetic tests, users and teams, read with the
 * connection's two keys on its site. Monitors, SLOs, tests and users are searched
 * by Datadog; dashboards have no search, so a search filters the page.
 */
class DatadogOptions extends HttpOptionsSource
{
    private const int PAGE_SIZE = 100;

    public function sources(): array
    {
        return ['datadog.monitors', 'datadog.dashboards', 'datadog.slos', 'datadog.synthetics', 'datadog.users', 'datadog.teams'];
    }

    protected function appName(): string
    {
        return 'Datadog';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $page = $this->intCursor($query, 0);
        $search = trim((string) $query->search);

        return match ($source) {
            'datadog.monitors' => $this->monitors($query, $search, $page),
            'datadog.dashboards' => $this->dashboards($query, $page),
            'datadog.slos' => $this->slos($query, $search, $page),
            'datadog.synthetics' => $this->synthetics($query, $search, $page),
            'datadog.users' => $this->users($query, $search, $page),
            default => $this->teams($query, $search, $page),
        };
    }

    private function monitors(NodeOptionsQuery $query, string $search, int $page): NodeOptionsPage
    {
        $body = $this->read($query, '/api/v1/monitor/search', ['query' => $search, 'page' => $page, 'per_page' => self::PAGE_SIZE]);
        $options = collect($body['monitors'] ?? [])
            ->filter(fn (mixed $monitor): bool => is_array($monitor) && isset($monitor['id']))
            ->map(fn (array $monitor): NodeOption => new NodeOption((string) $monitor['id'], (string) ($monitor['name'] ?? $monitor['id']), $monitor['status'] ?? null))
            ->values();

        return new NodeOptionsPage($options, $options->count() === self::PAGE_SIZE ? (string) ($page + 1) : null);
    }

    private function dashboards(NodeOptionsQuery $query, int $page): NodeOptionsPage
    {
        $body = $this->read($query, '/api/v1/dashboard', ['count' => self::PAGE_SIZE, 'start' => $page * self::PAGE_SIZE]);
        $rows = collect($body['dashboards'] ?? [])->filter(fn (mixed $dashboard): bool => is_array($dashboard) && isset($dashboard['id']));

        return new NodeOptionsPage(
            $rows->filter(fn (array $dashboard): bool => $query->matches((string) ($dashboard['title'] ?? '')))
                ->map(fn (array $dashboard): NodeOption => new NodeOption((string) $dashboard['id'], (string) ($dashboard['title'] ?? $dashboard['id']), $dashboard['layout_type'] ?? null))
                ->values(),
            $rows->count() === self::PAGE_SIZE ? (string) ($page + 1) : null,
        );
    }

    private function slos(NodeOptionsQuery $query, string $search, int $page): NodeOptionsPage
    {
        $body = $this->read($query, '/api/v1/slo', ['query' => $search, 'limit' => self::PAGE_SIZE, 'offset' => $page * self::PAGE_SIZE]);
        $options = collect($body['data'] ?? [])
            ->filter(fn (mixed $slo): bool => is_array($slo) && isset($slo['id']))
            ->map(fn (array $slo): NodeOption => new NodeOption((string) $slo['id'], (string) ($slo['name'] ?? $slo['id']), $slo['type'] ?? null))
            ->values();

        return new NodeOptionsPage($options, $options->count() === self::PAGE_SIZE ? (string) ($page + 1) : null);
    }

    private function synthetics(NodeOptionsQuery $query, string $search, int $page): NodeOptionsPage
    {
        $body = $this->read($query, '/api/v1/synthetics/tests/search', ['text' => $search, 'count' => self::PAGE_SIZE, 'start' => $page * self::PAGE_SIZE]);
        $options = collect($body['tests'] ?? [])
            ->filter(fn (mixed $test): bool => is_array($test) && isset($test['public_id']))
            ->map(fn (array $test): NodeOption => new NodeOption((string) $test['public_id'], (string) ($test['name'] ?? $test['public_id']), $test['type'] ?? null))
            ->values();

        return new NodeOptionsPage($options, $options->count() === self::PAGE_SIZE ? (string) ($page + 1) : null);
    }

    private function users(NodeOptionsQuery $query, string $search, int $page): NodeOptionsPage
    {
        $body = $this->read($query, '/api/v2/users', ['filter' => $search, 'page[size]' => self::PAGE_SIZE, 'page[number]' => $page]);
        $options = collect($body['data'] ?? [])
            ->filter(fn (mixed $user): bool => is_array($user) && isset($user['id']))
            ->map(fn (array $user): NodeOption => new NodeOption((string) $user['id'], (string) (data_get($user, 'attributes.name') ?: data_get($user, 'attributes.email') ?: $user['id']), data_get($user, 'attributes.email')))
            ->values();

        return new NodeOptionsPage($options, $options->count() === self::PAGE_SIZE ? (string) ($page + 1) : null);
    }

    private function teams(NodeOptionsQuery $query, string $search, int $page): NodeOptionsPage
    {
        $body = $this->read($query, '/api/v2/team', ['filter[keyword]' => $search, 'page[size]' => self::PAGE_SIZE, 'page[number]' => $page]);
        $options = collect($body['data'] ?? [])
            ->filter(fn (mixed $team): bool => is_array($team) && isset($team['id']))
            ->map(fn (array $team): NodeOption => new NodeOption((string) $team['id'], (string) (data_get($team, 'attributes.name') ?? $team['id']), data_get($team, 'attributes.handle')))
            ->values();

        return new NodeOptionsPage($options, $options->count() === self::PAGE_SIZE ? (string) ($page + 1) : null);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<mixed>
     *
     * @throws ConnectorException
     */
    private function read(NodeOptionsQuery $query, string $path, array $params): array
    {
        try {
            $response = AbstractDatadogNode::send($query->credentialData, 'GET', $path, $params);
        } catch (ConnectionException) {
            throw new ConnectorException("Couldn't reach Datadog. Try again in a moment.");
        } catch (InvalidArgumentException $exception) {
            throw new ConnectorException($exception->getMessage());
        }

        $this->ensureSuccessful($response, $path);

        $body = $response->json();

        return is_array($body) ? $body : [];
    }
}
