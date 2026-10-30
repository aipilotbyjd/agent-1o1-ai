<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Exa triggers through its Websets webhooks.
 *
 * One webhook per event kind, shared by the triggers that watch it. It covers all the
 * team's websets, and a delivery only makes each trigger's own poll look now, so a
 * trigger on one webset still sees only that webset. Exa signs its deliveries, but
 * the URL's random token (kept hashed) is the check and the secret is not needed.
 * Research runs and search monitors have no account-wide webhook, so those poll.
 */
class ExaSource extends AbstractTokenWatchedSource
{
    private const string API = 'https://api.exa.ai/websets/v0';

    public function provider(): string
    {
        return 'exa';
    }

    protected function nodeTypes(): array
    {
        return ['exa_list_webset_items', 'exa_list_events'];
    }

    /**
     * The event kind. "All events" can't be subscribed to, so it polls.
     */
    public function watchKeyFor(Trigger $trigger): ?string
    {
        if (($trigger->config['node_type'] ?? '') === 'exa_list_webset_items') {
            return 'webset.item.created';
        }

        $event = (string) ($this->nodeConfig($trigger)['event_type'] ?? '');

        return preg_match('/^[a-z]+(\.[a-z]+){1,2}$/', $event) === 1 ? $event : null;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Exa ends a webhook that keeps failing, or someone removed it: make it again if so.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $response = $this->exaHttp($this->credentialOf($watch))->get(self::API.'/webhooks/'.rawurlencode((string) $watch->external_id));

        if ($response->status() === 404 || ($response->successful() && $response->json('status') === 'inactive')) {
            $this->register($watch);

            return;
        }

        $this->ok($response);
        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $response = $this->exaHttp($this->credentialOf($watch))->delete(self::API.'/webhooks/'.rawurlencode((string) $watch->external_id));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Make the webhook for the watch's event, with a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        $this->registerUrl($watch, function (string $url) use ($watch): void {
            $response = $this->ok($this->exaHttp($this->credentialOf($watch))->post(self::API.'/webhooks', ['url' => $url, 'events' => [$watch->resource_key]]));

            if (! filled($response->json('id'))) {
                throw new WatchRefusedException('Exa did not create the webhook.');
            }

            $watch->external_id = (string) $response->json('id');
        });

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    private function exaHttp(ConnectorCredential $credential): PendingRequest
    {
        return Http::withHeaders(['x-api-key' => $this->tokens->accessToken($credential)])->acceptJson()->timeout(20);
    }
}
