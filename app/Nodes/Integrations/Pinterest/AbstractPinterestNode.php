<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Pinterest
 * node family, on API v5. The access token goes in a bearer header. A list answers
 * `{"items": [...], "bookmark": "..."}` and pages by sending the bookmark back
 * (`page_size` at most 250); there is no bookmark on the last page. Ids are long
 * numbers. A token from a Trial app only works against the sandbox, which is a
 * different host, so it cannot be used here.
 */
abstract class AbstractPinterestNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.pinterest.com/v5';

    public function category(): string
    {
        return 'pinterest';
    }

    /**
     * Call Pinterest and return the decoded body (empty for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, mixed>|null  $body  null values are dropped
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function pinterest(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->pinterestResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call Pinterest and return the response as it is, for a node that treats a 404 as an answer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function pinterestResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $body = array_filter($body, fn (mixed $value): bool => $value !== null);
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        return Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout(30)->send(strtoupper($method), self::API_URL.$path, $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('message') ?? "HTTP {$response->status()}");
        $code = $response->json('code');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Pinterest: the access token expired or was refused.',
            $response->status() === 403 => ' The token lacks the scope for this, or the app is still in trial access.',
            $response->status() === 429 => ' Pinterest is rate limiting the app; try again later.',
            default => '',
        };

        throw new RuntimeException('Pinterest error: '.mb_substr($message, 0, 400).($code ? " ({$code})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a list bookmark by bookmark until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, array $query, int $limit): array
    {
        $records = [];
        $bookmark = null;

        do {
            $page = $this->pinterest($run, $config, 'GET', $path, [...$query, 'page_size' => min(250, $limit - count($records)), 'bookmark' => $bookmark]);
            array_push($records, ...array_values(array_filter((array) ($page['items'] ?? []), 'is_array')));
            $bookmark = filled($page['bookmark'] ?? null) ? (string) $page['bookmark'] : null;
        } while ($bookmark !== null && count($records) < $limit);

        return array_slice($records, 0, $limit);
    }

    /**
     * An id (a long number) from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function numericId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return preg_match('/^\d{1,25}$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a Pinterest id (a long number).");
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function optionalId(array $config, string $key, string $label): ?string
    {
        return filled($config[$key] ?? null) ? $this->numericId($config, $key, $label) : null;
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function requiredString(array $config, string $key, string $label, int $max): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return mb_strlen($value) <= $max ? $value : throw new InvalidArgumentException("{$label} is at most {$max} characters.");
    }

    /**
     * An optional text, cut-checked against Pinterest's limit; null when empty.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function optionalString(array $config, string $key, string $label, int $max): ?string
    {
        if (! filled($config[$key] ?? null)) {
            return null;
        }

        $value = trim((string) $config[$key]);

        return mb_strlen($value) <= $max ? $value : throw new InvalidArgumentException("{$label} is at most {$max} characters.");
    }

    /**
     * A web address from a setting; null when empty.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function url(array $config, string $key, string $label): ?string
    {
        if (! filled($config[$key] ?? null)) {
            return null;
        }

        $url = trim((string) $config[$key]);

        return preg_match('~^https?://[^\s]{1,2040}$~i', $url) === 1 ? $url : throw new InvalidArgumentException("{$label} must start with http:// or https://.");
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default, int $max = 1000): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * @param  array<string, mixed>  $pin
     * @return array<string, mixed>
     */
    protected function shapePin(array $pin): array
    {
        $images = (array) data_get($pin, 'media.images', []);
        $image = $images['1200x']['url'] ?? $images['originals']['url'] ?? $images['600x']['url'] ?? (is_array(reset($images)) ? (reset($images)['url'] ?? null) : null);
        $id = isset($pin['id']) ? (string) $pin['id'] : null;

        return ['id' => $id, 'title' => $pin['title'] ?? null, 'description' => $pin['description'] ?? null, 'link' => $pin['link'] ?? null, 'alt_text' => $pin['alt_text'] ?? null, 'board_id' => isset($pin['board_id']) ? (string) $pin['board_id'] : null, 'board_section_id' => $pin['board_section_id'] ?? null, 'image_url' => $image, 'media_type' => data_get($pin, 'media.media_type'), 'created_at' => $pin['created_at'] ?? null, 'url' => $id !== null ? "https://www.pinterest.com/pin/{$id}/" : null];
    }

    /**
     * @param  array<string, mixed>  $board
     * @return array<string, mixed>
     */
    protected function shapeBoard(array $board): array
    {
        return ['id' => isset($board['id']) ? (string) $board['id'] : null, 'name' => $board['name'] ?? null, 'description' => $board['description'] ?? null, 'privacy' => $board['privacy'] ?? null, 'pin_count' => $board['pin_count'] ?? null, 'follower_count' => $board['follower_count'] ?? null, 'owner' => data_get($board, 'owner.username'), 'created_at' => $board['created_at'] ?? null, 'modified_at' => $board['board_pins_modified_at'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function boardField(string $title = 'Board'): array
    {
        return Field::dynamic($title, 'pinterest.boards', null, 'Recipes');
    }
}
