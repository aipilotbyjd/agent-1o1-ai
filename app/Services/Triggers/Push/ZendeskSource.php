<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Nodes\Integrations\Zendesk\AbstractZendeskNode;
use Illuminate\Http\Client\PendingRequest;
use InvalidArgumentException;

/**
 * Zendesk triggers through its webhooks.
 *
 * One webhook per kind of event, shared by the triggers that watch it. The URL
 * carries a random token (kept hashed); a delivery only makes the trigger's own
 * poll look now. Creating a webhook needs an admin; if not, the trigger keeps
 * polling.
 */
class ZendeskSource extends AbstractTokenWatchedSource
{
    /**
     * The event types a "ticket changed" webhook listens for.
     *
     * @var list<string>
     */
    private const array TICKET_CHANGES = [
        'zen:event-type:ticket.status_changed',
        'zen:event-type:ticket.priority_changed',
        'zen:event-type:ticket.subject_changed',
        'zen:event-type:ticket.description_changed',
        'zen:event-type:ticket.comment_added',
        'zen:event-type:ticket.agent_assignment_changed',
        'zen:event-type:ticket.group_assignment_changed',
        'zen:event-type:ticket.requester_changed',
        'zen:event-type:ticket.organization_changed',
        'zen:event-type:ticket.tags_changed',
        'zen:event-type:ticket.type_changed',
        'zen:event-type:ticket.custom_field_changed',
        'zen:event-type:ticket.custom_status_changed',
        'zen:event-type:ticket.form_changed',
        'zen:event-type:ticket.brand_changed',
        'zen:event-type:ticket.task_due_at_changed',
    ];

    /**
     * Each reader node's watch: what the webhook is filed under.
     *
     * @var array<string, string>
     */
    private const array WATCHES = [
        'zendesk_list_new_tickets' => 'ticket.created',
        'zendesk_list_updated_tickets' => 'ticket.changed',
        'zendesk_list_solved_tickets' => 'ticket.status_changed',
        'zendesk_list_closed_tickets' => 'ticket.status_changed',
        'zendesk_list_pending_tickets' => 'ticket.status_changed',
        'zendesk_list_tagged_tickets' => 'ticket.tags_changed',
        'zendesk_list_recent_comments' => 'ticket.comment_added',
        'zendesk_list_new_users' => 'user.created',
        'zendesk_list_tagged_users' => 'user.tags_changed',
        'zendesk_list_new_organizations' => 'organization.created',
    ];

    public function provider(): string
    {
        return 'zendesk';
    }

    protected function nodeTypes(): array
    {
        return array_keys(self::WATCHES);
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        return self::WATCHES[$trigger->config['node_type'] ?? ''] ?? null;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Someone may have removed or switched off the webhook: make it again if so.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        [$http, $root] = $this->client($this->credentialOf($watch));
        $response = $http->get("{$root}/webhooks/".rawurlencode((string) $watch->external_id));

        if ($response->status() === 404 || ($response->successful() && $response->json('webhook.status') === 'inactive')) {
            $this->register($watch);

            return;
        }

        $this->ok($response);
        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        [$http, $root] = $this->client($this->credentialOf($watch));
        $response = $http->delete("{$root}/webhooks/".rawurlencode((string) $watch->external_id));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Make the webhook for the watch's events, with a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        [$http, $root] = $this->client($this->credentialOf($watch));
        $old = $watch->external_id;
        $key = (string) $watch->resource_key;
        $events = $key === 'ticket.changed' ? self::TICKET_CHANGES : ['zen:event-type:'.$key];

        $this->registerUrl($watch, function (string $url) use ($watch, $http, $root, $key, $events): void {
            $response = $this->ok($http->post("{$root}/webhooks", ['webhook' => [
                'name' => 'Workflows '.$key,
                'endpoint' => $url,
                'http_method' => 'POST',
                'request_format' => 'json',
                'status' => 'active',
                'subscriptions' => $events,
            ]]));

            if (! filled($response->json('webhook.id'))) {
                throw new WatchRefusedException('Zendesk did not create the webhook.');
            }

            $watch->external_id = (string) $response->json('webhook.id');
        });

        // The old webhook, if there was one, points at a token that no longer exists.
        if ($old !== null && $old !== '' && $old !== $watch->external_id) {
            $http->delete("{$root}/webhooks/".rawurlencode((string) $old));
        }

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * An authorised client for the account, and its API root.
     *
     * @return array{0: PendingRequest, 1: string}
     *
     * @throws WatchRefusedException when the connection is incomplete
     */
    private function client(ConnectorCredential $credential): array
    {
        $data = (array) $credential->data;

        try {
            return [AbstractZendeskNode::client($data)->timeout(20), AbstractZendeskNode::apiRoot((string) ($data['subdomain'] ?? ''))];
        } catch (InvalidArgumentException $exception) {
            throw new WatchRefusedException($exception->getMessage());
        }
    }
}
