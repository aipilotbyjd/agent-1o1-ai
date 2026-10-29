<?php

namespace App\Services\Triggers\Push;

use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Support\Carbon;

/**
 * "New email" in Gmail through Gmail's watch and a Google Pub/Sub topic.
 *
 * One watch per mailbox, shared by every Gmail trigger on it. Gmail only says
 * "the mailbox changed", so the trigger's own poll fetches the new mail (and
 * applies its search). A watch lasts about a week; it is renewed daily.
 *
 * Setup outside the app: a Pub/Sub topic that Gmail may publish to
 * (gmail-api-push@system.gserviceaccount.com), and a push subscription on it
 * pointing at /api/hooks/apps/gmail?token={TRIGGERS_GMAIL_PUSH_TOKEN}.
 */
class GmailMessageSource extends AbstractWatchedSource
{
    private const string API = 'https://gmail.googleapis.com/gmail/v1/users/me';

    public function provider(): string
    {
        return 'gmail';
    }

    protected function nodeTypes(): array
    {
        return ['gmail_list_messages', 'gmail_list_messages_with_attachments'];
    }

    protected function isConfigured(): bool
    {
        return filled(config('triggers.push.gmail.topic')) && filled(config('triggers.push.gmail.verification_token'));
    }

    /**
     * One watch covers the whole mailbox.
     */
    public function watchKeyFor(Trigger $trigger): ?string
    {
        return 'mailbox';
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Calling `watch` again extends it.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $this->register($watch);
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $this->ok($this->http($this->credentialOf($watch))->post(self::API.'/stop'));
    }

    /**
     * The mailbox address Pub/Sub notifications name.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        $email = $watch?->meta['email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }

    private function register(ProviderWatch $watch): void
    {
        $http = $this->http($this->credentialOf($watch));

        $email = $this->ok($http->get(self::API.'/profile'))->json('emailAddress');
        $body = $this->ok($http->post(self::API.'/watch', [
            'topicName' => config('triggers.push.gmail.topic'),
            'labelIds' => ['INBOX'],
            'labelFilterBehavior' => 'INCLUDE',
        ]))->json();

        $expires = isset($body['expiration']) ? Carbon::createFromTimestampMs((int) $body['expiration']) : now()->addDays(7);

        $watch->external_id = strtolower((string) $email);
        $watch->meta = [...(array) $watch->meta, 'email' => strtolower((string) $email), 'history_id' => $body['historyId'] ?? null];
        $watch->expires_at = $expires;
        $watch->renew_at = min(now()->addDay(), $expires->copy()->subHours(6));
    }
}
