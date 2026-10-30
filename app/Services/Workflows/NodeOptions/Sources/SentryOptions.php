<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Nodes\Integrations\Sentry\AbstractSentryNode;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\ConnectionException;
use InvalidArgumentException;

/**
 * The token's organizations, and the projects, teams and members of the node's
 * organization (the connection's when the node has none). Sentry searches these
 * itself and pages with a cursor in its `Link` header.
 */
class SentryOptions extends HttpOptionsSource
{
    private const int PAGE_SIZE = 100;

    public function sources(): array
    {
        return ['sentry.organizations', 'sentry.projects', 'sentry.teams', 'sentry.members'];
    }

    protected function appName(): string
    {
        return 'Sentry';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        if ($source === 'sentry.organizations') {
            return $this->page($query, '/api/0/organizations/', fn (array $organization): NodeOption => new NodeOption((string) ($organization['slug'] ?? ''), (string) ($organization['name'] ?? $organization['slug'] ?? ''), $organization['slug'] ?? null));
        }

        $org = trim((string) ($query->configString('organization') ?: ($query->credentialData['organization'] ?? '')));

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,99}$/', $org) !== 1) {
            return new NodeOptionsPage(collect());
        }

        return match ($source) {
            'sentry.projects' => $this->page($query, "/api/0/organizations/{$org}/projects/", fn (array $project): NodeOption => new NodeOption((string) ($project['slug'] ?? ''), (string) ($project['name'] ?? $project['slug'] ?? ''), $project['platform'] ?? null)),
            'sentry.teams' => $this->page($query, "/api/0/organizations/{$org}/teams/", fn (array $team): NodeOption => new NodeOption((string) ($team['slug'] ?? ''), (string) ($team['name'] ?? $team['slug'] ?? ''), $team['slug'] ?? null)),
            default => $this->page($query, "/api/0/organizations/{$org}/members/", fn (array $member): NodeOption => new NodeOption((string) ($member['id'] ?? ''), (string) (($member['name'] ?? '') ?: ($member['email'] ?? '')), $member['email'] ?? null)),
        };
    }

    /**
     * @param  callable(array<mixed>): NodeOption  $option
     *
     * @throws ConnectorException
     */
    private function page(NodeOptionsQuery $query, string $path, callable $option): NodeOptionsPage
    {
        try {
            $response = AbstractSentryNode::send($query->credentialData, 'GET', $path, ['query' => $query->search, 'per_page' => self::PAGE_SIZE, 'cursor' => $query->cursor]);
        } catch (ConnectionException) {
            throw new ConnectorException("Couldn't reach Sentry. Try again in a moment.");
        } catch (InvalidArgumentException $exception) {
            throw new ConnectorException($exception->getMessage());
        }

        $this->ensureSuccessful($response, $path);

        $options = collect((array) ($response->json() ?? []))
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): NodeOption => $option($row))
            ->filter(fn (NodeOption $node): bool => $node->value !== '')
            ->values();

        return new NodeOptionsPage($options, AbstractSentryNode::nextCursor($response));
    }
}
