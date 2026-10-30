<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Nodes\Integrations\Apollo\AbstractApolloNode;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Apollo's team members, stages, sequences, lists, mailboxes, schedules and saved
 * records. Contacts, accounts and sequences are searched by Apollo itself; the rest
 * come as a whole list, so a search filters what came back.
 */
class ApolloOptions extends HttpOptionsSource
{
    private const int PAGE_SIZE = 50;

    public function sources(): array
    {
        return ['apollo.users', 'apollo.contact_stages', 'apollo.account_stages', 'apollo.deal_stages', 'apollo.sequences', 'apollo.lists', 'apollo.email_accounts', 'apollo.email_schedules', 'apollo.accounts', 'apollo.contacts', 'apollo.deals'];
    }

    protected function appName(): string
    {
        return 'Apollo';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['x-api-key' => (string) $query->token, 'Cache-Control' => 'no-cache'])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $page = $this->intCursor($query);

        return match ($source) {
            'apollo.users' => $this->paged($query, $page, (array) ($this->get($query, '/users/search', ['page' => $page, 'per_page' => self::PAGE_SIZE])['users'] ?? []), 'name', 'email'),
            'apollo.contact_stages' => $this->named($query, (array) ($this->get($query, '/contact_stages')['contact_stages'] ?? [])),
            'apollo.account_stages' => $this->named($query, (array) ($this->get($query, '/account_stages')['account_stages'] ?? [])),
            'apollo.deal_stages' => $this->named($query, (array) ($this->get($query, '/opportunity_stages')['opportunity_stages'] ?? [])),
            'apollo.lists' => $this->named($query, $this->get($query, '/labels')),
            'apollo.email_accounts' => $this->named($query, (array) ($this->get($query, '/email_accounts')['email_accounts'] ?? []), 'email'),
            'apollo.email_schedules' => $this->named($query, (array) ($this->get($query, '/emailer_schedules')['emailer_schedules'] ?? [])),
            'apollo.deals' => $this->paged($query, $page, (array) ($this->get($query, '/opportunities/search', ['page' => $page, 'per_page' => self::PAGE_SIZE])['opportunities'] ?? []), 'name', null),
            'apollo.sequences' => $this->paged($query, $page, (array) ($this->post($query, '/emailer_campaigns/search?'.AbstractApolloNode::queryString(['q_name' => $query->search, 'page' => $page, 'per_page' => self::PAGE_SIZE]))['emailer_campaigns'] ?? []), 'name', null),
            'apollo.accounts' => $this->paged($query, $page, (array) ($this->post($query, '/accounts/search', ['q_organization_name' => $query->search, 'page' => $page, 'per_page' => self::PAGE_SIZE])['accounts'] ?? []), 'name', 'domain'),
            default => $this->paged($query, $page, (array) ($this->post($query, '/contacts/search', ['q_keywords' => $query->search, 'page' => $page, 'per_page' => self::PAGE_SIZE])['contacts'] ?? []), 'name', 'email'),
        };
    }

    /**
     * A whole list, filtered by the search here.
     *
     * @param  array<mixed>  $items
     */
    private function named(NodeOptionsQuery $query, array $items, string $label = 'name'): NodeOptionsPage
    {
        $options = collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && $query->matches((string) ($item[$label] ?? '')))
            ->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], isset($item[$label]) ? (string) $item[$label] : null));

        return new NodeOptionsPage($options);
    }

    /**
     * One page of a list Apollo pages itself.
     *
     * @param  array<mixed>  $items
     */
    private function paged(NodeOptionsQuery $query, int $page, array $items, string $label, ?string $detail): NodeOptionsPage
    {
        $options = collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']))
            ->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], $this->labelOf($item, $label), $detail === null ? null : ($item[$detail] ?? null)));

        return new NodeOptionsPage($options, count($items) === self::PAGE_SIZE ? $page + 1 : null);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function labelOf(array $item, string $label): ?string
    {
        $text = $item[$label] ?? trim(($item['first_name'] ?? '').' '.($item['last_name'] ?? ''));

        return is_string($text) && $text !== '' ? $text : null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<mixed>
     *
     * @throws ConnectorException
     */
    private function get(NodeOptionsQuery $query, string $path, array $params = []): array
    {
        return $this->getJson($query, AbstractApolloNode::BASE_URL.$path, $params);
    }

    /**
     * Some of Apollo's lists are searches, which it takes as a POST.
     *
     * @param  array<string, mixed>  $body
     * @return array<mixed>
     *
     * @throws ConnectorException
     */
    private function post(NodeOptionsQuery $query, string $path, array $body = []): array
    {
        $url = AbstractApolloNode::BASE_URL.$path;

        try {
            $response = $this->http($query)->post($url, array_filter($body, fn (mixed $value): bool => $value !== null));
        } catch (ConnectionException) {
            throw new ConnectorException("Couldn't reach {$this->appName()}. Try again in a moment.");
        }

        $this->ensureSuccessful($response, $url);

        return (array) ($response->json() ?? []);
    }
}
