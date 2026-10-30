<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The account's boards, and the sections of the chosen board, a page at a time (the
 * bookmark is the cursor). Pinterest can't search them, so a search filters the page.
 */
class PinterestOptions extends HttpOptionsSource
{
    private const string API = 'https://api.pinterest.com/v5';

    private const int PAGE_SIZE = 100;

    public function sources(): array
    {
        return ['pinterest.boards', 'pinterest.sections'];
    }

    protected function appName(): string
    {
        return 'Pinterest';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withToken((string) $query->token)->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $path = '/boards';

        if ($source === 'pinterest.sections') {
            $board = trim((string) $query->configString('board_id'));

            if (preg_match('/^\d+$/', $board) !== 1) {
                return new NodeOptionsPage([]);
            }

            $path = "/boards/{$board}/sections";
        }

        $body = $this->getJson($query, self::API.$path, ['page_size' => self::PAGE_SIZE, 'bookmark' => $query->cursor]);
        $items = collect($body['items'] ?? [])->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && $query->matches((string) ($item['name'] ?? '')));

        return new NodeOptionsPage(
            $items->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], (string) ($item['name'] ?? $item['id']), $source === 'pinterest.boards' ? ($item['privacy'] ?? null) : null)),
            filled($body['bookmark'] ?? null) ? (string) $body['bookmark'] : null,
        );
    }
}
