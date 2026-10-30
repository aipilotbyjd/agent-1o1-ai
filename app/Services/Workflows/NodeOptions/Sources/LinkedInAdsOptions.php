<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Nodes\Integrations\LinkedInAds\AbstractLinkedInAdsNode;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * LinkedIn ad accounts, and the campaign groups, campaigns, lead forms, matched
 * audiences and conversion rules of the chosen one. The query strings are written out
 * because Rest.li needs its brackets left alone. A page token is LinkedIn's own, sent
 * back as one encoded value.
 */
class LinkedInAdsOptions extends HttpOptionsSource
{
    private const int PAGE_SIZE = 100;

    public function sources(): array
    {
        return ['linkedin_ads.accounts', 'linkedin_ads.campaign_groups', 'linkedin_ads.campaigns', 'linkedin_ads.lead_forms', 'linkedin_ads.segments', 'linkedin_ads.conversions'];
    }

    protected function appName(): string
    {
        return 'LinkedIn Ads';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withToken((string) $query->token)->withHeaders(['Linkedin-Version' => AbstractLinkedInAdsNode::VERSION, 'X-Restli-Protocol-Version' => '2.0.0'])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        if ($source === 'linkedin_ads.accounts') {
            return $this->searched($query, '/adAccounts', 'q=search&search=(status:(values:List(ACTIVE,DRAFT)))');
        }

        $account = $this->accountId($query);
        $urn = rawurlencode("urn:li:sponsoredAccount:{$account}");

        return match ($source) {
            'linkedin_ads.campaign_groups' => $this->searched($query, "/adAccounts/{$account}/adCampaignGroups", 'q=search&search=(status:(values:List(ACTIVE,DRAFT,PAUSED)))&sortOrder=DESCENDING'),
            'linkedin_ads.campaigns' => $this->searched($query, "/adAccounts/{$account}/adCampaigns", 'q=search&search=(status:(values:List(ACTIVE,DRAFT,PAUSED)))&sortOrder=DESCENDING'),
            'linkedin_ads.lead_forms' => $this->indexed($query, '/leadForms', "q=owner&owner=(sponsoredAccount:{$urn})"),
            'linkedin_ads.segments' => $this->indexed($query, '/dmpSegments', "q=account&account={$urn}"),
            default => $this->indexed($query, '/conversions', "q=account&account={$urn}"),
        };
    }

    /**
     * The ad account field's value: a number, or a URN ending in one.
     *
     * @throws ConnectorException
     */
    private function accountId(NodeOptionsQuery $query): string
    {
        $value = (string) $query->configString('account_id', '');
        $id = str_contains($value, ':') ? substr((string) strrchr($value, ':'), 1) : $value;

        return ctype_digit($id) ? $id : throw new ConnectorException('Choose an ad account first.');
    }

    /**
     * A search with cursor paging.
     *
     * @throws ConnectorException
     */
    private function searched(NodeOptionsQuery $query, string $path, string $queryString): NodeOptionsPage
    {
        $token = $query->cursor === null ? '' : '&pageToken='.rawurlencode($query->cursor);
        $body = $this->read($query, $path.'?'.$queryString.'&pageSize='.self::PAGE_SIZE.$token);

        return new NodeOptionsPage($this->options($query, $body), data_get($body, 'metadata.nextPageToken'));
    }

    /**
     * A finder with `start` and `count` paging.
     *
     * @throws ConnectorException
     */
    private function indexed(NodeOptionsQuery $query, string $path, string $queryString): NodeOptionsPage
    {
        $start = $this->intCursor($query, 0);
        $body = $this->read($query, $path.'?'.$queryString.'&count='.self::PAGE_SIZE."&start={$start}");
        $found = count((array) ($body['elements'] ?? []));

        return new NodeOptionsPage($this->options($query, $body), $found === self::PAGE_SIZE ? $start + self::PAGE_SIZE : null);
    }

    /**
     * A GET whose written-out query must stay in the URL: the shared helper's empty
     * query list would replace it.
     *
     * @return array<mixed>
     *
     * @throws ConnectorException
     */
    private function read(NodeOptionsQuery $query, string $pathAndQuery): array
    {
        $url = AbstractLinkedInAdsNode::BASE_URL.$pathAndQuery;

        try {
            $response = $this->http($query)->send('GET', $url);
        } catch (ConnectionException) {
            throw new ConnectorException("Couldn't reach {$this->appName()}. Try again in a moment.");
        }

        $this->ensureSuccessful($response, $url);

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<mixed>  $body
     * @return list<NodeOption>
     */
    private function options(NodeOptionsQuery $query, array $body): array
    {
        return collect((array) ($body['elements'] ?? []))
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && $query->matches((string) ($item['name'] ?? '')))
            ->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], isset($item['name']) ? (string) $item['name'] : null, isset($item['status']) ? (string) $item['status'] : ($item['state'] ?? null)))
            ->values()->all();
    }
}
