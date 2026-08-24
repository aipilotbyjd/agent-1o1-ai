<?php

namespace App\Nodes\DataTransform;

use App\Contracts\NodeContract;
use App\Enums\NodeCategory;
use App\Exceptions\Http\BlockedUrlException;
use App\Models\Runs\Run;
use App\Services\Http\SsrfGuard;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Generic outbound HTTP request node (Gumloop's "Call API"/`CallApiNode`).
 */
class CallApiNode implements NodeContract
{
    private const MAX_REDIRECTS = 5;

    public function __construct(private readonly SsrfGuard $ssrfGuard = new SsrfGuard) {}

    public function type(): string
    {
        return 'call_api';
    }

    public function category(): string
    {
        return NodeCategory::DataTransform->value;
    }

    public function name(): string
    {
        return 'Call API';
    }

    public function description(): string
    {
        return 'Makes a generic outbound HTTP request and returns the status, headers, and body.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['method', 'url'],
            'properties' => [
                'method' => ['type' => 'string', 'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']],
                'url' => ['type' => 'string'],
                'headers' => ['type' => 'object'],
                'body' => ['type' => 'object'],
                'timeout_seconds' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->sendGuarded(
            strtoupper($config['method'] ?? 'GET'),
            $config['url'],
            $config['headers'] ?? [],
            $config['body'] ?? [],
            (int) ($config['timeout_seconds'] ?? 30),
        );

        return [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->json() ?? $response->body(),
        ];
    }

    /**
     * Validates the URL against {@see SsrfGuard} before every request —
     * including each redirect hop, since a server that resolves safely on
     * the first request can still 302 a workflow author's request into an
     * internal address. Redirects are followed manually (rather than via
     * Guzzle's `allow_redirects`) so each `Location` gets that same check
     * before the client ever connects to it.
     *
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $body
     */
    private function sendGuarded(string $method, string $url, array $headers, array $body, int $timeoutSeconds, int $redirectsLeft = self::MAX_REDIRECTS): Response
    {
        $this->ssrfGuard->assertUrlIsAllowed($url);

        $response = Http::withHeaders($headers)
            ->timeout($timeoutSeconds)
            ->withOptions(['allow_redirects' => false])
            ->send($method, $url, ['json' => $body]);

        $location = $response->header('Location');

        if ($response->redirect() && $location !== null) {
            if ($redirectsLeft <= 0) {
                throw BlockedUrlException::forUrl($url, 'too many redirects.');
            }

            return $this->sendGuarded($method, $this->resolveRedirectUrl($url, $location), $headers, $body, $timeoutSeconds, $redirectsLeft - 1);
        }

        return $response;
    }

    private function resolveRedirectUrl(string $requestUrl, string $location): string
    {
        if (parse_url($location, PHP_URL_HOST) !== null) {
            return $location;
        }

        // Relative `Location` header — resolve it against the request URL's
        // origin rather than rejecting it, since same-origin relative
        // redirects are the common case and carry no additional risk once
        // the original host has already passed the guard.
        $base = parse_url($requestUrl);

        $origin = sprintf('%s://%s%s', $base['scheme'], $base['host'], isset($base['port']) ? ':'.$base['port'] : '');

        return $origin.'/'.ltrim($location, '/');
    }
}
