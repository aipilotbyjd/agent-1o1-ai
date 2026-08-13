<?php

namespace App\Jobs\Connectors;

use App\Enums\Queue;
use App\Models\Connectors\ConnectorCredential;
use App\Notifications\Connectors\ConnectorCredentialExpiredNotification;
use App\Services\Connectors\OAuthConnectorFlowService;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Refreshes an OAuth `ConnectorCredential` before/after its access token
 * expires. On final failure (all retries exhausted), the credential is
 * marked expired and the workspace's owners/admins are notified so a human
 * can reconnect it — mirrors the old project's `RefreshOAuthTokenJob`.
 */
class RefreshConnectorCredentialJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public int $tries = 3;

    public function __construct(public ConnectorCredential $credential)
    {
        $this->onQueue(Queue::Maintenance->value);
    }

    public function handle(OAuthConnectorFlowService $flow): void
    {
        $flow->refresh($this->credential);
    }

    public function failed(?Throwable $exception): void
    {
        $this->credential->update(['expires_at' => now()]);

        app(NotificationDispatcher::class)->dispatch(
            app(NotificationDispatcher::class)->ownersAndAdmins($this->credential->workspace),
            new ConnectorCredentialExpiredNotification($this->credential),
        );
    }
}
