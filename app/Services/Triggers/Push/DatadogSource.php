<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Nodes\Integrations\Datadog\AbstractDatadogNode;
use InvalidArgumentException;

/**
 * Datadog monitor triggers through its webhooks integration.
 *
 * Datadog calls a webhook only for a monitor whose message mentions it
 * (`@webhook-name`). So this is opt-in per trigger ("Instant delivery") and needs one
 * chosen monitor: the watch makes a webhook with a token URL, adds the mention to that
 * monitor's message, and takes it out again when the watch goes away. A delivery only
 * makes the trigger's own poll look now.
 */
class DatadogSource extends AbstractTokenWatchedSource
{
    private const string WEBHOOKS = '/api/v1/integration/webhooks/configuration/webhooks';

    /**
     * What Datadog posts. The values are Datadog's own template variables.
     */
    private const string PAYLOAD = '{"id":"$ID","alert_id":"$ALERT_ID","transition":"$ALERT_TRANSITION","title":"$EVENT_TITLE","date":"$DATE"}';

    public function provider(): string
    {
        return 'datadog';
    }

    protected function nodeTypes(): array
    {
        return ['datadog_list_monitor_state_changes', 'datadog_list_monitor_alerts'];
    }

    /**
     * Only a trigger that asked for it, on one monitor.
     */
    public function supports(Trigger $trigger): bool
    {
        $config = $this->nodeConfig($trigger);

        return parent::supports($trigger) && ($config['instant_delivery'] ?? false) === true && $this->monitorOf($config) !== null;
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $monitor = $this->monitorOf($this->nodeConfig($trigger));

        return $monitor === null ? null : "monitor:{$monitor}";
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Someone may have removed the webhook, or edited the mention out of the message: put back what is gone.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $data = $this->dataOf($this->credentialOf($watch));
        $name = (string) $watch->external_id;
        $response = AbstractDatadogNode::send($data, 'GET', self::WEBHOOKS.'/'.rawurlencode($name));

        if ($response->status() === 404) {
            $this->register($watch);

            return;
        }

        $this->ok($response);
        $this->mention($data, $this->monitorId($watch), $name);
        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $data = $this->dataOf($this->credentialOf($watch));
        $name = (string) $watch->external_id;
        $path = '/api/v1/monitor/'.$this->monitorId($watch);
        $monitor = AbstractDatadogNode::send($data, 'GET', $path);

        if ($monitor->successful()) {
            $message = (string) $monitor->json('message');
            $cleaned = (string) preg_replace('/\s*@webhook-'.preg_quote($name, '/').'(?![\w-])/', '', $message);

            if ($cleaned !== $message) {
                $this->ok(AbstractDatadogNode::send($data, 'PUT', $path, [], ['message' => $cleaned]));
            }
        }

        $response = AbstractDatadogNode::send($data, 'DELETE', self::WEBHOOKS.'/'.rawurlencode($name));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Make the webhook with a fresh token URL, and mention it in the monitor's message.
     */
    private function register(ProviderWatch $watch): void
    {
        $data = $this->dataOf($this->credentialOf($watch));
        $monitor = $this->monitorId($watch);
        $existing = filled($watch->external_id);
        $name = $existing ? (string) $watch->external_id : 'flows-'.$monitor.'-'.substr($this->newSecret(), 0, 10);

        $this->registerUrl($watch, function (string $url) use ($watch, $data, $monitor, $name, $existing): void {
            $body = ['url' => $url, 'payload' => self::PAYLOAD, 'encode_as' => 'json'];
            $response = $existing ? AbstractDatadogNode::send($data, 'PUT', self::WEBHOOKS.'/'.rawurlencode($name), [], $body) : null;

            if ($response === null || $response->status() === 404) {
                $response = AbstractDatadogNode::send($data, 'POST', self::WEBHOOKS, [], ['name' => $name, ...$body]);
            }

            $this->ok($response);
            $this->mention($data, $monitor, $name);
            $watch->external_id = $name;
        }, ['monitor_id' => $monitor]);

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * Add the mention to the monitor's message, unless it is there.
     *
     * @param  array<string, mixed>  $data
     */
    private function mention(array $data, string $monitor, string $name): void
    {
        $path = "/api/v1/monitor/{$monitor}";
        $message = (string) $this->ok(AbstractDatadogNode::send($data, 'GET', $path))->json('message');

        if (preg_match('/@webhook-'.preg_quote($name, '/').'(?![\w-])/', $message) !== 1) {
            $this->ok(AbstractDatadogNode::send($data, 'PUT', $path, [], ['message' => rtrim($message)."\n@webhook-{$name}"]));
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function monitorOf(array $config): ?string
    {
        $monitor = trim((string) ($config['monitor_id'] ?? ''));

        return preg_match('/^\d{1,20}$/', $monitor) === 1 ? $monitor : null;
    }

    /**
     * The monitor a watch is on, from its key.
     */
    private function monitorId(ProviderWatch $watch): string
    {
        return substr((string) $watch->resource_key, strlen('monitor:'));
    }

    /**
     * The connection's fields, checked so a bad site can't carry the keys anywhere.
     *
     * @return array<string, mixed>
     *
     * @throws WatchRefusedException
     */
    private function dataOf(ConnectorCredential $credential): array
    {
        $data = (array) $credential->data;

        try {
            AbstractDatadogNode::siteHost((string) ($data['site'] ?? ''));
        } catch (InvalidArgumentException $exception) {
            throw new WatchRefusedException($exception->getMessage());
        }

        if (blank($data['api_key'] ?? null) || blank($data['app_key'] ?? null)) {
            throw new WatchRefusedException('Add both Datadog keys to the connection.');
        }

        return $data;
    }
}
