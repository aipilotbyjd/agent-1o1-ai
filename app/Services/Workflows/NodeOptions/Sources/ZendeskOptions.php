<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Nodes\Integrations\Zendesk\AbstractZendeskNode;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use InvalidArgumentException;

/**
 * The groups, agents, views, macros, organizations, ticket forms and brands of a
 * Zendesk account. A list is read one page at a time; a search filters what came
 * back, except organizations, which Zendesk can look up by name.
 */
class ZendeskOptions extends HttpOptionsSource
{
    public function sources(): array
    {
        return ['zendesk.groups', 'zendesk.agents', 'zendesk.views', 'zendesk.macros', 'zendesk.organizations', 'zendesk.ticket_forms', 'zendesk.brands'];
    }

    protected function appName(): string
    {
        return 'Zendesk';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        try {
            return AbstractZendeskNode::client($query->credentialData)->timeout(self::TIMEOUT_SECONDS);
        } catch (InvalidArgumentException $exception) {
            throw new ConnectorException($exception->getMessage());
        }
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        try {
            $root = AbstractZendeskNode::apiRoot((string) ($query->credentialData['subdomain'] ?? ''));
        } catch (InvalidArgumentException $exception) {
            throw new ConnectorException($exception->getMessage());
        }

        $page = $this->intCursor($query);

        if ($source === 'zendesk.organizations' && filled($query->search)) {
            $rows = $this->getJson($query, $root.'/organizations/autocomplete', ['name' => $query->search])['organizations'] ?? [];

            return new NodeOptionsPage(collect($rows)->filter(fn (mixed $row): bool => is_array($row) && isset($row['id']))->map(fn (array $row): NodeOption => new NodeOption((string) $row['id'], (string) ($row['name'] ?? $row['id']))));
        }

        [$path, $key, $label, $params] = match ($source) {
            'zendesk.agents' => ['/users/search', 'users', 'name', ['query' => 'role:agent role:admin']],
            'zendesk.views' => ['/views', 'views', 'title', ['active' => 'true']],
            'zendesk.macros' => ['/macros', 'macros', 'title', ['active' => 'true']],
            'zendesk.organizations' => ['/organizations', 'organizations', 'name', []],
            'zendesk.ticket_forms' => ['/ticket_forms', 'ticket_forms', 'name', ['active' => 'true']],
            'zendesk.brands' => ['/brands', 'brands', 'name', []],
            default => ['/groups', 'groups', 'name', []],
        };

        $answer = $this->getJson($query, $root.$path, [...$params, 'page' => $page, 'per_page' => 100]);
        $rows = collect($answer[$key] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && isset($row['id']) && $query->matches((string) ($row[$label] ?? ''), (string) ($row['email'] ?? '')));

        return new NodeOptionsPage(
            $rows->map(fn (array $row): NodeOption => new NodeOption((string) $row['id'], (string) ($row[$label] ?? $row['id']), $row['email'] ?? null)),
            filled($answer['next_page'] ?? null) ? $page + 1 : null,
        );
    }
}
