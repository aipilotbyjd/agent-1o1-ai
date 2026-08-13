<?php

namespace App\Services\Connectors;

use App\Exceptions\ConnectorException;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Connectors\OAuthConnectorState;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
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
    public function initiate(Workspace $workspace, User $user, Connector $connector, string $name, string $redirectUri): array
    {
        if (! $connector->isOAuth()) {
            throw new ConnectorException("Connector [{$connector->key}] does not support OAuth.");
        }

        $state = Str::random(40);

        OAuthConnectorState::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'state' => $state,
            'name' => $name,
            'redirect_uri' => $redirectUri,
            'expires_at' => now()->addMinutes(self::STATE_TTL_MINUTES),
        ]);

        $params = http_build_query([
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

        $response = Http::asForm()->post($connector->oauth['token_url'], [
            'client_id' => $this->clientId($connector),
            'client_secret' => $this->clientSecret($connector),
            'code' => $code,
            'redirect_uri' => $pending->redirect_uri,
            'grant_type' => 'authorization_code',
        ]);

        if ($response->failed()) {
            throw new ConnectorException("Failed to exchange OAuth code for connector [{$connector->key}]: {$response->body()}");
        }

        $credential = ConnectorCredential::create([
            'workspace_id' => $pending->workspace_id,
            'connector_id' => $connector->id,
            'created_by' => $pending->user_id,
            'name' => $pending->name,
            'data' => $this->tokenData($response->json()),
            'expires_at' => $this->expiresAt($response->json()),
        ]);

        $pending->delete();

        return $credential;
    }

    public function refresh(ConnectorCredential $credential): ConnectorCredential
    {
        $connector = $credential->connector;
        $refreshToken = $credential->data['refresh_token'] ?? null;

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new ConnectorException("Connector credential [{$credential->id}] has no refresh_token to refresh with.");
        }

        $response = Http::asForm()->post($connector->oauth['token_url'], [
            'client_id' => $this->clientId($connector),
            'client_secret' => $this->clientSecret($connector),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            throw new ConnectorException("Failed to refresh connector credential [{$credential->id}]: {$response->body()}");
        }

        $body = $response->json();

        $credential->update([
            'data' => [...$credential->data, ...$this->tokenData($body)],
            'expires_at' => $this->expiresAt($body) ?? $credential->expires_at,
        ]);

        return $credential->fresh();
    }

    private function clientId(Connector $connector): string
    {
        return config("services.{$connector->key}.client_id");
    }

    private function clientSecret(Connector $connector): string
    {
        return config("services.{$connector->key}.client_secret");
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
