<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Nodes\Integrations\Freshdesk\AbstractFreshdeskNode;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use InvalidArgumentException;

/**
 * The groups, agents, companies, products, sending addresses, knowledge base and
 * forum categories and canned response folders of a Freshdesk account. A search
 * filters what came back.
 */
class FreshdeskOptions extends HttpOptionsSource
{
    public function sources(): array
    {
        return ['freshdesk.groups', 'freshdesk.agents', 'freshdesk.companies', 'freshdesk.products', 'freshdesk.email_configs', 'freshdesk.solution_categories', 'freshdesk.forum_categories', 'freshdesk.canned_response_folders'];
    }

    protected function appName(): string
    {
        return 'Freshdesk';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        try {
            return AbstractFreshdeskNode::client($query->credentialData)->timeout(self::TIMEOUT_SECONDS);
        } catch (InvalidArgumentException $exception) {
            throw new ConnectorException($exception->getMessage());
        }
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        try {
            $root = AbstractFreshdeskNode::apiRoot((string) ($query->credentialData['subdomain'] ?? ''));
        } catch (InvalidArgumentException $exception) {
            throw new ConnectorException($exception->getMessage());
        }

        $path = match ($source) {
            'freshdesk.agents' => '/agents',
            'freshdesk.companies' => '/companies',
            'freshdesk.products' => '/products',
            'freshdesk.email_configs' => '/email_configs',
            'freshdesk.solution_categories' => '/solutions/categories',
            'freshdesk.forum_categories' => '/discussions/categories',
            'freshdesk.canned_response_folders' => '/canned_response_folders',
            default => '/groups',
        };

        $page = $this->intCursor($query);
        $paged = in_array($source, ['freshdesk.groups', 'freshdesk.agents', 'freshdesk.companies', 'freshdesk.products', 'freshdesk.email_configs'], true);
        $rows = $this->getJson($query, $root.$path, $paged ? ['page' => $page, 'per_page' => 100] : []);
        $label = fn (array $row): string => (string) ($row['name'] ?? data_get($row, 'contact.name') ?? $row['id']);
        $matches = collect($rows)->filter(fn (mixed $row): bool => is_array($row) && isset($row['id']) && $query->matches($label($row), (string) ($row['email'] ?? $row['reply_email'] ?? data_get($row, 'contact.email') ?? '')));

        return new NodeOptionsPage(
            $matches->map(fn (array $row): NodeOption => new NodeOption((string) $row['id'], $label($row), $row['reply_email'] ?? data_get($row, 'contact.email'))),
            $paged && count($rows) === 100 ? $page + 1 : null,
        );
    }
}
