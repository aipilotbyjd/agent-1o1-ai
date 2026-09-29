<?php

namespace App\Services\Http;

use App\Exceptions\Http\BlockedUrlException;

/**
 * Guards outbound-HTTP nodes (Call API, and anything added later that fetches
 * a workflow-author-supplied URL) against SSRF: a workflow author is any
 * authenticated user, and without this a "make an HTTP request" node lets
 * them make the server fetch cloud metadata endpoints or internal-network
 * services on their behalf.
 *
 * Every call site must re-validate on each redirect hop too — checking only
 * the original URL and then blindly following `Location` headers reopens the
 * same hole one hop later.
 */
class SsrfGuard
{
    /**
     * @var list<string>
     */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * @var callable(string): list<string>
     */
    private $resolver;

    /**
     * @param  (callable(string): list<string>)|null  $resolver  Resolves a
     *                                                           hostname to its IP addresses. Defaults to a real DNS lookup; tests
     *                                                           substitute a fake so assertions don't depend on outbound network
     *                                                           access being available.
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver ?? $this->dnsResolve(...);
    }

    public function assertUrlIsAllowed(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw BlockedUrlException::forUrl($url, 'the URL could not be parsed.');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw BlockedUrlException::forUrl($url, "the \"{$scheme}\" scheme is not allowed.");
        }

        $this->assertHostIsAllowed($parts['host'], $url);
    }

    private function assertHostIsAllowed(string $host, string $url): void
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->assertIpIsAllowed($host, $url);

            return;
        }

        $ips = ($this->resolver)($host);

        if ($ips === []) {
            throw BlockedUrlException::forUrl($url, "the host \"{$host}\" could not be resolved.");
        }

        foreach ($ips as $ip) {
            $this->assertIpIsAllowed($ip, $url);
        }
    }

    /**
     * @return list<string>
     */
    private function dnsResolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));
    }

    /**
     * Rejects loopback, link-local (including the 169.254.169.254 cloud
     * metadata address), and RFC1918 private ranges — the two filter flags
     * combined are PHP's standard "is this a routable public address" check.
     */
    private function assertIpIsAllowed(string $ip, string $url): void
    {
        $isPublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        if ($isPublic === false) {
            throw BlockedUrlException::forUrl($url, "the host resolves to a non-public address ({$ip}).");
        }
    }
}
