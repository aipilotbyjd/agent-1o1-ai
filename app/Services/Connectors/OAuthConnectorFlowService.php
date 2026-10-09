<?php

namespace App\Services\Connectors;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Exceptions\ConnectorException;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Connectors\OAuthConnectorState;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Drives the OAuth2 authorization-code flow for `oauth2` connectors:
 * `initiate()` builds the provider authorize URL behind a short-lived CSRF
 * `state` row, `handleCallback()` exchanges the returned `code` for tokens
 * and stores them as a `ConnectorCredential`, and `refresh()` re-exchanges a
 * `refresh_token` when the stored access token expires. Client id/secret
 * come from `config/services.php` (Socialite's own convention) — never
 * stored on the `Connector` row itself.
 */
class OAuthConnectorFlowService
{
    private const int STATE_TTL_MINUTES = 10;

    /**
     * @return array{authorize_url: string, state: string}
     */
    public function initiate(
        Workspace $workspace,
        User $user,
        Connector $connector,
        string $name,
        string $redirectUri,
        ?string $scope = null,
        ?ConnectorCredential $reconnecting = null,
    ): array {
        if (! $connector->isOAuth()) {
            throw new ConnectorException("Connector [{$connector->key}] does not support OAuth.");
        }

        if (! $connector->isConfigured()) {
            throw ConnectorException::notConfigured($connector);
        }

        $state = Str::random(40);

        OAuthConnectorState::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'connector_credential_id' => $reconnecting?->id,
            'state' => $state,
            'name' => $reconnecting?->name ?? $name,
            'redirect_uri' => $redirectUri,
            'scope' => $reconnecting?->scope->value ?? $scope ?? ConnectorCredentialScope::Team->value,
            'expires_at' => now()->addMinutes(self::STATE_TTL_MINUTES),
        ]);

        // Provider-specific extras, e.g. Google only returns a refresh token
        // (and so a connection that outlives its one-hour access token) when
        // asked for offline access with consent.
        $params = http_build_query([
            ...($connector->oauth['authorize_params'] ?? []),
            'client_id' => $this->clientId($connector),
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $connector->oauth['scopes'] ?? []),
            'state' => $state,
            'response_type' => 'code',
        ]);

        return [
            'authorize_url' => $connector->oauth['authorize_url']."?{$params}",
            'state' => $state,
        ];
    }

    public function handleCallback(string $state, string $code): ConnectorCredential
    {
        $pending = OAuthConnectorState::where('state', $state)->first();

        if ($pending === null || $pending->expires_at->isPast()) {
            throw new ConnectorException('OAuth state is invalid or has expired.');
        }

        $connector = $pending->connector;
        $clientId = $this->clientId($connector);
        $clientSecret = $this->clientSecret($connector);

        // Single use, claimed before any network call: the delete only
        // succeeds for one of two simultaneous callbacks carrying the same state.
        if (OAuthConnectorState::query()->whereKey($pending->getKey())->delete() === 0) {
            throw new ConnectorException('OAuth state is invalid or has expired.');
        }

        $response = Http::acceptJson()->asForm()->timeout(30)->connectTimeout(10)->post($connector->oauth['token_url'], [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $pending->redirect_uri,
            'grant_type' => 'authorization_code',
        ]);

        if ($response->failed()) {
            $this->logProviderFailure('exchange', $connector, $response);

            throw new ConnectorException("Failed to exchange OAuth code for connector [{$connector->key}] (HTTP {$response->status()}). Please try connecting again.");
        }

        $body = $this->validatedTokenBody($response->json());

        $reconnecting = $pending->connector_credential_id === null ? null : ConnectorCredential::query()
            ->where('workspace_id', $pending->workspace_id)
            ->where('connector_id', $connector->id)
            ->find($pending->connector_credential_id);

        if ($reconnecting !== null) {
            return $this->renew($reconnecting, $body);
        }

        $credential = ConnectorCredential::create([
            'workspace_id' => $pending->workspace_id,
            'connector_id' => $connector->id,
            'created_by' => $pending->user_id,
            'scope' => $pending->scope,
            'name' => $pending->name,
            'data' => $this->tokenData($body),
            'expires_at' => $this->expiresAt($body),
        ]);

        return $credential;
    }

    /**
     * Fresh tokens for an existing account. The new token set replaces the
     * old one (and with it any `refresh_rejected_at` mark); a provider that
     * doesn't re-issue a refresh token keeps the old one, unless that was
     * the one it rejected.
     *
     * @param  array<string, mixed>  $body
     */
    private function renew(ConnectorCredential $credential, array $body): ConnectorCredential
    {
        $data = $this->tokenData($body);
        $previous = $credential->data;

        if (! isset($data['refresh_token']) && filled($previous['refresh_token'] ?? null) && blank($previous['refresh_rejected_at'] ?? null)) {
            $data['refresh_token'] = $previous['refresh_token'];
        }

        $credential->update([
            'data' => $data,
            'expires_at' => $this->expiresAt($body),
        ]);

        return $credential;
    }

    public function refresh(ConnectorCredential $credential): ConnectorCredential
    {
        $connector = $credential->connector;
        $refreshToken = $credential->data['refresh_token'] ?? null;

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new ConnectorException("Connector credential [{$credential->id}] has no refresh_token to refresh with.");
        }

        $response = Http::acceptJson()->asForm()->timeout(30)->connectTimeout(10)->post($connector->oauth['token_url'], [
            'client_id' => $this->clientId($connector),
            'client_secret' => $this->clientSecret($connector),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            $this->logProviderFailure('refresh', $connector, $response);

            throw new ConnectorException("Failed to refresh connector credential [{$credential->id}] (HTTP {$response->status()}). Please reconnect the connector.");
        }

        $body = $this->validatedTokenBody($response->json());

        $credential->update([
            'data' => [...$credential->data, ...$this->tokenData($body)],
            'expires_at' => $this->expiresAt($body) ?? $credential->expires_at,
        ]);

        return $credential->fresh();
    }

    /**
     * The provider's reply stays in the logs (bounded) — it can echo request
     * details and is not for the API client.
     */
    private function logProviderFailure(string $operation, Connector $connector, Response $response): void
    {
        Log::warning("OAuth token {$operation} failed.", [
            'connector' => $connector->key,
            'status' => $response->status(),
            'body' => Str::limit($response->body(), 500),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTokenBody(mixed $body): array
    {
        if (! is_array($body) || isset($body['error']) || ! is_string($body['access_token'] ?? null) || trim($body['access_token']) === '') {
            throw new ConnectorException('OAuth provider did not return a valid access token. Please reconnect the connector.');
        }

        return $body;
    }

    private function clientId(Connector $connector): string
    {
        return $this->clientConfig($connector, 'client_id');
    }

    private function clientSecret(Connector $connector): string
    {
        return $this->clientConfig($connector, 'client_secret');
    }

    /**
     * A callback or refresh can still arrive after the config was removed.
     */
    private function clientConfig(Connector $connector, string $key): string
    {
        $value = config("services.{$connector->key}.{$key}");

        if (! is_string($value) || $value === '') {
            throw ConnectorException::notConfigured($connector);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function tokenData(array $body): array
    {
        return array_filter([
            'access_token' => $body['access_token'] ?? null,
            'refresh_token' => $body['refresh_token'] ?? null,
            'token_type' => $body['token_type'] ?? null,
            'scope' => $body['scope'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function expiresAt(array $body): ?Carbon
    {
        return isset($body['expires_in']) ? now()->addSeconds((int) $body['expires_in']) : null;
    }
}
