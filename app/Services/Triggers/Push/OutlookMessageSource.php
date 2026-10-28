<?php

namespace App\Services\Triggers\Push;

use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;

/**
 * "New email" in Outlook through a Microsoft Graph change subscription on a
 * mail folder, shared by every Outlook trigger on that folder.
 *
 * Mail subscriptions last under three days, so they are renewed every two.
 * Graph posts a notification (validated with the watch's secret as
 * `clientState`) and the trigger's own poll fetches the mail.
 */
class OutlookMessageSource extends AbstractWatchedSource
{
    private const string API = 'https://graph.microsoft.com/v1.0';

    /**
     * Graph's longest lifetime for a mail subscription is 4230 minutes.
     */
    private const int LIFETIME_MINUTES = 4200;

    public function provider(): string
    {
        return 'outlook';
    }

    protected function nodeTypes(): array
    {
        return ['outlook_list_messages'];
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        return 'folder:'.$this->folder($trigger);
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $folder = str_starts_with($watch->resource_key, 'folder:') ? substr($watch->resource_key, 7) : 'inbox';

        $this->create($watch, $folder);
    }

    /**
     * Extend the subscription; if Graph has forgotten it, make a new one.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $http = $this->http($this->credentialOf($watch));
        $expires = now()->addMinutes(self::LIFETIME_MINUTES);

        try {
            $this->ok($http->patch(self::API.'/subscriptions/'.$watch->external_id, ['expirationDateTime' => $expires->toIso8601ZuluString()]));
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 404) {
                throw $exception;
            }

            $this->create($watch, substr($watch->resource_key, 7));

            return;
        }

        $watch->expires_at = $expires;
        $watch->renew_at = now()->addDays(2);
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        if ($watch->external_id !== null) {
            $this->http($this->credentialOf($watch))->delete(self::API.'/subscriptions/'.$watch->external_id);
        }
    }

    /**
     * Graph names the subscription in every notification.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        return $watch?->external_id;
    }

    private function create(ProviderWatch $watch, string $folder): void
    {
        $expires = Carbon::now()->addMinutes(self::LIFETIME_MINUTES);
        $secret = $this->newSecret();

        $body = $this->ok($this->http($this->credentialOf($watch))->post(self::API.'/subscriptions', [
            'changeType' => 'created',
            'notificationUrl' => $this->callbackUrl(),
            'lifecycleNotificationUrl' => $this->callbackUrl(),
            'resource' => "me/mailFolders('{$folder}')/messages",
            'expirationDateTime' => $expires->toIso8601ZuluString(),
            'clientState' => $secret,
        ]))->json();

        $watch->external_id = (string) $body['id'];
        $watch->secret = $secret;
        $watch->expires_at = $expires;
        $watch->renew_at = now()->addDays(2);
    }

    private function folder(Trigger $trigger): string
    {
        $folder = $this->nodeConfig($trigger)['folder'] ?? null;

        return is_string($folder) && $folder !== '' ? $folder : 'inbox';
    }
}
