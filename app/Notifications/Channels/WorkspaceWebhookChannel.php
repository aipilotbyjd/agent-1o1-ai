<?php

namespace App\Notifications\Channels;

use App\Models\Notifications\NotificationChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkspaceWebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWorkspaceChannel')) {
            return;
        }

        $payload = $notification->toWorkspaceChannel($notifiable);
        $channelIds = $payload['channel_ids'];

        if ($channelIds === []) {
            return;
        }

        NotificationChannel::query()
            ->where('workspace_id', $payload['workspace_id'])
            ->whereIn('id', $channelIds)
            ->where('is_active', true)
            ->each(fn (NotificationChannel $channel) => $this->deliver($channel, $payload['message']));
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function deliverTest(NotificationChannel $channel): array
    {
        return $this->deliver($channel, 'This is a test notification from your workspace.');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function deliver(NotificationChannel $channel, string $message): array
    {
        try {
            $config = $channel->config;
            $request = Http::timeout(10)->retry(2, 250);

            $response = match ($channel->type) {
                'discord' => $request->post($config['url'], ['content' => $message]),
                'slack' => $request->post($config['url'], ['text' => $message]),
                'webhook' => $request->withHeaders($config['headers'] ?? [])->post($config['url'], ['message' => $message]),
            };

            if ($response->successful()) {
                return ['ok' => true, 'message' => 'Delivered.'];
            }

            Log::warning('Workspace notification channel delivery failed.', [
                'channel_id' => $channel->id,
                'status' => $response->status(),
            ]);

            return ['ok' => false, 'message' => "Delivery failed: HTTP {$response->status()}."];
        } catch (Throwable $exception) {
            Log::warning('Workspace notification channel delivery errored.', [
                'channel_id' => $channel->id,
                'exception' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Delivery error.'];
        }
    }
}
