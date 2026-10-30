<?php

namespace App\Nodes\Integrations\LinkedIn;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use App\Services\Http\GuardedHttp;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the LinkedIn
 * node family, on the versioned REST API (`/rest`) plus the OpenID `userinfo` call.
 * Every call carries the API version and the Rest.li protocol header. A person,
 * company page or post is named by a URN (`urn:li:person:…`,
 * `urn:li:organization:123`, `urn:li:share:123`), which sits URL-encoded in a path.
 * A create answers 201 with no body and the new id in the `x-restli-id` header.
 * A post's text is "little text format", where `( ) [ ] { } < > @ # * _ ~ | \` are
 * markup, so plain text is escaped unless the node is told it is already markup.
 */
abstract class AbstractLinkedInNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.linkedin.com';

    /**
     * The `LinkedIn-Version` header (YYYYMM). LinkedIn retires versions after about a year.
     */
    public const string API_VERSION = '202506';

    private const int MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    public function category(): string
    {
        return 'linkedin';
    }

    /**
     * Call LinkedIn and return the decoded body (empty for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function linkedin(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->linkedinResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call LinkedIn and return the response as it is, for a node that reads a header or treats a 404 as an answer.
     * The path starts with `/rest` or `/v2`.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function linkedinResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        return Http::withToken($this->resolveAccessToken($run, $config))
            ->withHeaders(['LinkedIn-Version' => self::API_VERSION, 'X-Restli-Protocol-Version' => '2.0.0'])
            ->acceptJson()->timeout(30)
            ->send(strtoupper($method), self::API_URL.$path, $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('message') ?? $response->json('error_description') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect LinkedIn: the access token expired (they last 60 days) or was refused.',
            $response->status() === 403 => ' The token lacks the scope for this, or the user is not allowed to act for that page.',
            $response->status() === 426 => ' LinkedIn no longer accepts this API version; the app needs updating.',
            $response->status() === 429 => ' LinkedIn is rate limiting the app; try again later.',
            default => '',
        };

        throw new RuntimeException('LinkedIn error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * The URN of the member the token belongs to.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws RuntimeException
     */
    protected function personUrn(Run $run, array $config): string
    {
        $id = $this->linkedin($run, $config, 'GET', '/v2/userinfo')['sub'] ?? null;

        return filled($id) ? 'urn:li:person:'.$id : throw new RuntimeException('LinkedIn did not say who this token belongs to. The token needs the openid and profile scopes.');
    }

    /**
     * Who a post is made as: empty or "me" is the member; a number or an organization URN is a company page.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function authorUrn(Run $run, array $config, string $key = 'author'): string
    {
        $author = trim((string) ($config[$key] ?? ''));

        return match (true) {
            $author === '' || strtolower($author) === 'me' => $this->personUrn($run, $config),
            preg_match('/^\d{1,20}$/', $author) === 1 => 'urn:li:organization:'.$author,
            preg_match('/^urn:li:(person:[A-Za-z0-9_-]{1,64}|organization:\d{1,20})$/', $author) === 1 => $author,
            default => throw new InvalidArgumentException('The author must be empty (you), a company page, or a URN like urn:li:organization:123.'),
        };
    }

    /**
     * A post URN from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function postUrn(array $config, string $key = 'post_id'): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException('The post is required.');
        }

        return preg_match('/^urn:li:(share|ugcPost|activity):\d{1,25}$/', $value) === 1 ? $value : throw new InvalidArgumentException('The post must be a LinkedIn post URN like urn:li:share:123.');
    }

    /**
     * Post text ready for LinkedIn: escaped unless the node is told it is already markup.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function commentary(array $config, string $key = 'text', bool $required = true): string
    {
        $text = trim((string) ($config[$key] ?? ''));

        if ($text === '') {
            return $required ? throw new InvalidArgumentException('The text is required.') : '';
        }

        if (mb_strlen($text) > 3000) {
            throw new InvalidArgumentException('LinkedIn posts are at most 3000 characters.');
        }

        return ($config['markup'] ?? false) ? $text : preg_replace('/([\\\\|{}@\[\]()<>#*_~])/', '\\\\$1', $text);
    }

    /**
     * Create a post and return its URN from the `x-restli-id` header.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $content  an `article` or `media` block
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function publish(Run $run, array $config, string $author, string $text, ?array $content = null): array
    {
        $visibility = ($config['visibility'] ?? 'PUBLIC') === 'CONNECTIONS' ? 'CONNECTIONS' : 'PUBLIC';

        if ($visibility === 'CONNECTIONS' && str_starts_with($author, 'urn:li:organization:')) {
            throw new InvalidArgumentException('A company page can only post publicly.');
        }

        $response = $this->linkedinResponse($run, $config, 'POST', '/rest/posts', [], array_filter([
            'author' => $author,
            'commentary' => $text,
            'visibility' => $visibility,
            'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []],
            'content' => $content,
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => (bool) ($config['disable_reshare'] ?? false),
        ], fn (mixed $value): bool => $value !== null));

        $this->assertOk($response);

        $id = $response->header('x-restli-id');

        return ['posted' => true, 'id' => $id !== '' ? $id : null, 'author' => $author, 'url' => $id !== '' ? $this->postUrl($id) : null];
    }

    /**
     * Download an image from a public URL (guarded against internal addresses) and upload it to LinkedIn.
     *
     * @param  array<string, mixed>  $config
     * @return string the image URN
     *
     * @throws RuntimeException
     */
    protected function uploadImage(Run $run, array $config, string $author, string $url): string
    {
        if (preg_match('~^https?://~i', $url) !== 1) {
            throw new InvalidArgumentException('The image address must start with http:// or https://.');
        }

        $download = app(GuardedHttp::class)->send('GET', $url, ['Accept' => 'image/*'], timeoutSeconds: 30);

        if (! $download->successful()) {
            throw new RuntimeException("The image could not be downloaded (HTTP {$download->status()}).");
        }

        $bytes = $download->body();
        $type = strtolower(trim(explode(';', (string) $download->header('Content-Type'))[0]));

        if ($bytes === '' || strlen($bytes) > self::MAX_IMAGE_BYTES || ! in_array($type, ['image/jpeg', 'image/png', 'image/gif'], true)) {
            throw new InvalidArgumentException('The image must be a JPEG, PNG or GIF of at most 8 MB.');
        }

        $init = $this->linkedin($run, $config, 'POST', '/rest/images', ['action' => 'initializeUpload'], ['initializeUploadRequest' => ['owner' => $author]]);
        $uploadUrl = $init['value']['uploadUrl'] ?? null;
        $image = $init['value']['image'] ?? null;

        if (! filled($uploadUrl) || ! filled($image)) {
            throw new RuntimeException('LinkedIn did not give an upload address for the image.');
        }

        $put = Http::withToken($this->resolveAccessToken($run, $config))->withBody($bytes, $type)->timeout(60)->put($uploadUrl);

        if (! $put->successful()) {
            throw new RuntimeException("LinkedIn refused the image upload (HTTP {$put->status()}).");
        }

        return (string) $image;
    }

    protected function postUrl(string $urn): string
    {
        return 'https://www.linkedin.com/feed/update/'.$urn.'/';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default, int $max = 100): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * A time in milliseconds since 1970 as an ISO string.
     */
    protected function iso(mixed $millis): ?string
    {
        return is_numeric($millis) ? gmdate('c', intdiv((int) $millis, 1000)) : null;
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    protected function shapePost(array $post): array
    {
        $id = isset($post['id']) ? (string) $post['id'] : null;

        return ['id' => $id, 'author' => $post['author'] ?? null, 'text' => $post['commentary'] ?? null, 'visibility' => $post['visibility'] ?? null, 'state' => $post['lifecycleState'] ?? null, 'kind' => isset($post['content']['article']) ? 'article' : (isset($post['content']['media']) ? 'media' : 'text'), 'article_url' => $post['content']['article']['source'] ?? null, 'created_at' => $this->iso($post['createdAt'] ?? null), 'published_at' => $this->iso($post['publishedAt'] ?? null), 'url' => $id !== null ? $this->postUrl($id) : null];
    }

    /**
     * @param  array<string, mixed>  $comment
     * @return array<string, mixed>
     */
    protected function shapeComment(array $comment): array
    {
        return ['id' => isset($comment['id']) ? (string) $comment['id'] : (isset($comment['$URN']) ? (string) $comment['$URN'] : null), 'post' => $comment['object'] ?? null, 'author' => $comment['actor'] ?? null, 'text' => $comment['message']['text'] ?? null, 'created_at' => $this->iso($comment['created']['time'] ?? null)];
    }

    /**
     * @return array<string, mixed>
     */
    protected function authorField(string $title = 'Post as'): array
    {
        return Field::advanced(Field::dynamic($title, 'linkedin.organizations', 'Empty posts as you. Choose a company page you administer to post as the page.', 'Acme Inc'));
    }
}
