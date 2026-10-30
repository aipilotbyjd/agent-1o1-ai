<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Nodes\Integrations\LinkedIn\AbstractLinkedInNode;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The company pages the member administers, by page id (what a node needs to act as
 * the page). LinkedIn lists the roles first and names each page separately, so at
 * most twenty are named. A search filters what came back.
 */
class LinkedInOptions extends HttpOptionsSource
{
    private const string API = 'https://api.linkedin.com/rest';

    public function sources(): array
    {
        return ['linkedin.organizations'];
    }

    protected function appName(): string
    {
        return 'LinkedIn';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withToken((string) $query->token)->withHeaders(['LinkedIn-Version' => AbstractLinkedInNode::API_VERSION, 'X-Restli-Protocol-Version' => '2.0.0'])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $roles = collect($this->getJson($query, self::API.'/organizationAcls', ['q' => 'roleAssignee', 'role' => 'ADMINISTRATOR', 'state' => 'APPROVED', 'count' => 20])['elements'] ?? [])
            ->map(fn (mixed $role): ?string => is_array($role) && preg_match('/^urn:li:organization:(\d+)$/', (string) ($role['organization'] ?? ''), $match) === 1 ? $match[1] : null)
            ->filter()
            ->take(20);

        $options = $roles
            ->map(fn (string $id): array => ['id' => $id, 'name' => (string) ($this->getJson($query, self::API."/organizations/{$id}")['localizedName'] ?? $id)])
            ->filter(fn (array $page): bool => $query->matches($page['name']))
            ->map(fn (array $page): NodeOption => new NodeOption($page['id'], $page['name']));

        return new NodeOptionsPage($options->values());
    }
}
