<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Firecrawl monitor triggers through the monitor's webhook.
 *
 * A monitor is the one thing a private key can give a lasting webhook to: crawls and
 * batch scrapes only post to the address given when they start, and there is no
 * account-wide webhook, so those triggers poll. A monitor has one webhook, so one
 * already set by someone else is left alone and the trigger keeps polling. A delivery
 * only makes the trigger's own poll look now, so the URL's random token (kept hashed)
 * is the check and the account's signing secret is not needed.
 */
class FirecrawlSource extends AbstractTokenWatchedSource
{
    private const string API = 'https://api.firecrawl.dev/v2';

    public function provider(): string
    {
        return 'firecrawl';
    }

    protected function nodeTypes(): array
    {
        return ['firecrawl_list_monitor_checks', 'firecrawl_list_monitor_changes'];
    }

    /**
     * The monitor's id. One filled from an earlier step can't be watched.
     */
    public function watchKeyFor(Trigger $trigger): ?string
    {
        $monitor = trim((string) ($this->nodeConfig($trigger)['monitor_id'] ?? ''));

        return preg_match('/^[A-Za-z0-9-]{1,100}$/', $monitor) === 1 ? $monitor : null;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $http = $this->firecrawlHttp($this->credentialOf($watch));
        $existing = $this->ok($http->get($this->monitorUrl($watch)))->json('data.webhook.url');

        if (filled($existing) && ! str_starts_with((string) $existing, $this->callbackUrl())) {
            throw new WatchRefusedException('This monitor already posts to another address, so the trigger checks it every few minutes instead.');
        }

        $this->register($watch);
    }

    /**
     * The webhook may have been replaced or cleared: claim it again only if it is free.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $response = $this->firecrawlHttp($this->credentialOf($watch))->get($this->monitorUrl($watch));

        if ($response->status() === 404) {
            throw new WatchRefusedException('This monitor no longer exists.');
        }

        $this->ok($response);

        if (! filled($response->json('data.webhook.url'))) {
            $this->register($watch);

            return;
        }

        $watch->renew_at = now()->addDay();
    }

    /**
     * Clears the webhook, unless someone has since pointed it elsewhere.
     */
    public function deleteWatch(ProviderWatch $watch): void
    {
        $http = $this->firecrawlHttp($this->credentialOf($watch));
        $response = $http->get($this->monitorUrl($watch));

        if ($response->status() === 404) {
            return;
        }

        $this->ok($response);

        if (str_starts_with((string) $response->json('data.webhook.url'), $this->callbackUrl())) {
            $this->ok($http->patch($this->monitorUrl($watch), ['webhook' => null]));
        }
    }

    /**
     * Point the monitor's webhook at a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        $this->registerUrl($watch, function (string $url) use ($watch): void {
            $this->ok($this->firecrawlHttp($this->credentialOf($watch))->patch($this->monitorUrl($watch), ['webhook' => ['url' => $url, 'events' => ['monitor.check.completed']]]));
            $watch->external_id = (string) $watch->resource_key;
        });

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    private function monitorUrl(ProviderWatch $watch): string
    {
        return self::API.'/monitor/'.rawurlencode((string) $watch->resource_key);
    }

    private function firecrawlHttp(ConnectorCredential $credential): PendingRequest
    {
        return Http::withToken($this->tokens->accessToken($credential))->acceptJson()->timeout(20);
    }
}
