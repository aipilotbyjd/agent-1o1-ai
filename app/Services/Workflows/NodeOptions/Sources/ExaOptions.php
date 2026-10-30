<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The team's websets and search monitors. Exa takes the key in `x-api-key`. A search
 * filters the newest page, which is all a member usually needs to pick from.
 */
class ExaOptions extends HttpOptionsSource
{
    public function sources(): array
    {
        return ['exa.websets', 'exa.monitors'];
    }

    protected function appName(): string
    {
        return 'Exa';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['x-api-key' => (string) $query->token])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $url = $source === 'exa.websets' ? 'https://api.exa.ai/websets/v0/websets' : 'https://api.exa.ai/monitors';
        $items = collect($this->getJson($query, $url, ['limit' => 100])['data'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']))
            ->filter(fn (array $item): bool => $query->matches((string) ($item['title'] ?? $item['name'] ?? '')));

        return new NodeOptionsPage($items->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], $item['title'] ?? $item['name'] ?? null, $item['status'] ?? null)));
    }
}
