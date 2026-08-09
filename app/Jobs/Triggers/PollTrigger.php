<?php

namespace App\Jobs\Triggers;

use App\Enums\Triggers\TriggerType;
use App\Models\Triggers\Trigger;
use App\Services\Triggers\TriggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches new items for a polling trigger and queues one TriggerEvent per new
 * item found. The endpoint shape comes from the trigger's (preset-merged)
 * config: `url`, `method`, `items_path`, `id_path`, `cursor_path`,
 * `cursor_param`. Authenticated polling (`credential_id`) is wired in once
 * ConnectorCredential exists (docs/NODES_CATALOG.md) — until then this only
 * supports unauthenticated/public polling endpoints.
 */
class PollTrigger implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Trigger $trigger)
    {
        $this->onQueue((string) config('triggers.poll_queue'));
    }

    public function handle(TriggerService $triggers): void
    {
        $this->trigger->refresh();

        if (! $this->trigger->is_active) {
            return;
        }

        $config = array_merge((array) ($this->trigger->preset?->config ?? []), (array) $this->trigger->config);
        $url = $config['url'] ?? null;

        if ($url === null) {
            $triggers->recordFailure($this->trigger);

            return;
        }

        try {
            $method = strtolower((string) ($config['method'] ?? 'get'));
            $response = Http::withQueryParameters($this->cursorQuery($config))->{$method}($url);
            $response->throw();
        } catch (Throwable) {
            $triggers->recordFailure($this->trigger);
            $this->trigger->update(['last_run_at' => now()]);

            return;
        }

        $items = (array) (data_get($response->json(), $config['items_path'] ?? null) ?? []);

        foreach ($items as $item) {
            $deliveryId = (string) data_get($item, $config['id_path'] ?? 'id');

            $triggers->receive($this->trigger, TriggerType::Polling, (array) $item, $deliveryId);
        }

        $this->trigger->update([
            'poll_cursor' => $items === [] ? $this->trigger->poll_cursor : [
                'value' => data_get(end($items), $config['cursor_path'] ?? ($config['id_path'] ?? 'id')),
            ],
            'last_run_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function cursorQuery(array $config): array
    {
        $cursorParam = $config['cursor_param'] ?? null;
        $cursorValue = $this->trigger->poll_cursor['value'] ?? null;

        if ($cursorParam === null || $cursorValue === null) {
            return [];
        }

        return [$cursorParam => $cursorValue];
    }
}
