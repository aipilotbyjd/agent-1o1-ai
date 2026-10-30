<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Nodes\Integrations\MetaAds\AbstractMetaAdsNode;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Meta's ad accounts, Pages and businesses, and the campaigns, ad sets, ads, creatives,
 * audiences and pixels of the chosen ad account, plus a Page's lead forms and a
 * business's catalogs. Pages are Graph cursors, sent back as one value and never the
 * `next` address, which the client could swap for any other.
 */
class MetaAdsOptions extends HttpOptionsSource
{
    private const int PAGE_SIZE = 100;

    /**
     * Set while listing a Page's forms, which needs the Page's own token.
     */
    private ?string $pageAccessToken = null;

    public function sources(): array
    {
        return ['meta_ads.accounts', 'meta_ads.pages', 'meta_ads.businesses', 'meta_ads.campaigns', 'meta_ads.ad_sets', 'meta_ads.ads', 'meta_ads.creatives', 'meta_ads.audiences', 'meta_ads.pixels', 'meta_ads.lead_forms', 'meta_ads.catalogs'];
    }

    protected function appName(): string
    {
        return 'Meta Ads';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withToken($this->pageAccessToken ?? (string) $query->token)->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return match ($source) {
            'meta_ads.accounts' => $this->listed($query, '/me/adaccounts', 'id,name,account_status', 'account_status'),
            'meta_ads.pages' => $this->listed($query, '/me/accounts', 'id,name,category', 'category'),
            'meta_ads.businesses' => $this->listed($query, '/me/businesses', 'id,name'),
            'meta_ads.catalogs' => $this->listed($query, '/'.$this->numeric($query, 'business_id', 'a business').'/owned_product_catalogs', 'id,name'),
            'meta_ads.lead_forms' => $this->leadForms($query),
            'meta_ads.campaigns' => $this->listed($query, '/'.$this->account($query).'/campaigns', 'id,name,effective_status', 'effective_status'),
            'meta_ads.ad_sets' => $this->listed($query, '/'.$this->account($query).'/adsets', 'id,name,effective_status', 'effective_status'),
            'meta_ads.ads' => $this->listed($query, '/'.$this->account($query).'/ads', 'id,name,effective_status', 'effective_status'),
            'meta_ads.creatives' => $this->listed($query, '/'.$this->account($query).'/adcreatives', 'id,name,status', 'status'),
            'meta_ads.audiences' => $this->listed($query, '/'.$this->account($query).'/customaudiences', 'id,name,subtype', 'subtype'),
            default => $this->listed($query, '/'.$this->account($query).'/adspixels', 'id,name'),
        };
    }

    /**
     * @throws ConnectorException
     */
    private function listed(NodeOptionsQuery $query, string $path, string $fields, ?string $detail = null): NodeOptionsPage
    {
        $body = $this->getJson($query, AbstractMetaAdsNode::BASE_URL.$path, ['fields' => $fields, 'limit' => self::PAGE_SIZE, 'after' => $query->cursor]);

        $options = collect((array) ($body['data'] ?? []))
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && $query->matches((string) ($item['name'] ?? '')))
            ->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], isset($item['name']) ? (string) $item['name'] : null, $detail !== null && isset($item[$detail]) ? (string) $item[$detail] : null));

        return new NodeOptionsPage($options, isset($body['paging']['next']) ? data_get($body, 'paging.cursors.after') : null);
    }

    /**
     * @throws ConnectorException
     */
    private function account(NodeOptionsQuery $query): string
    {
        $value = (string) $query->configString('account_id', '');
        $id = str_starts_with($value, 'act_') ? substr($value, 4) : $value;

        return ctype_digit($id) ? "act_{$id}" : throw new ConnectorException('Choose an ad account first.');
    }

    /**
     * @throws ConnectorException
     */
    private function numeric(NodeOptionsQuery $query, string $key, string $label): string
    {
        $value = (string) $query->configString($key, '');

        return ctype_digit($value) ? $value : throw new ConnectorException("Choose {$label} first.");
    }

    /**
     * @throws ConnectorException
     */
    private function leadForms(NodeOptionsQuery $query): NodeOptionsPage
    {
        $page = $this->numeric($query, 'page_id', 'a Page');
        $token = $this->getJson($query, AbstractMetaAdsNode::BASE_URL."/{$page}", ['fields' => 'access_token'])['access_token'] ?? null;

        $this->pageAccessToken = is_string($token) ? $token : null;

        return $this->listed($query, "/{$page}/leadgen_forms", 'id,name,status', 'status');
    }
}
