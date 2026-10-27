<?php

namespace App\Contracts\Triggers;

use Illuminate\Http\Request;

/**
 * The public door a provider delivers events to: checks they are genuine,
 * answers the provider's handshake, and pulls the events out of a request.
 *
 * It does nothing slow: ProviderIngressController stores what it returns and
 * the provider is answered at once.
 */
interface ProviderIngress
{
    /**
     * The provider key (`/api/hooks/apps/{provider}`).
     */
    public function provider(): string;

    /**
     * Whether the request really comes from the provider.
     */
    public function verify(Request $request): bool;

    /**
     * The reply to a one-off handshake request, or null when the request is an
     * ordinary event delivery.
     *
     * @return array<string, mixed>|null
     */
    public function handshake(Request $request): ?array;

    /**
     * The events in a delivery.
     *
     * @return list<array{event_id: string, event_type: string, routing_key: ?string, payload: array<string, mixed>}>
     */
    public function events(Request $request): array;
}
